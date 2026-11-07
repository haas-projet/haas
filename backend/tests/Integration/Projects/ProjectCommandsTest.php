<?php

namespace Tests\Integration\Projects;

use App\Data\Idempotency\IdempotencyKey;
use App\Data\Projects\CreateProjectData;
use App\Enums\Identity\AccountStatus;
use App\Enums\Projects\ProjectPublicationState;
use App\Models\Project;
use App\Models\Technology;
use App\Models\User;
use App\Services\Projects\CreateProjectService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class ProjectCommandsTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    private const string KEY = '6ac01a00-3333-4444-8888-123456789abc';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_creation_is_private_idempotent_atomic_and_uses_inert_links(): void
    {
        $this->freezeTime();
        Http::fake();
        $owner = User::factory()->verified()->create();
        $this->login($owner);
        $tech = Technology::factory()->create();
        $body = ['name' => "\u{00a0}Projet pédagogique\u{00a0}", 'project_stage' => 'prototype',
            'demo_url' => 'https://project.example.test/demo', 'repository_url' => 'https://code.example.test/project', 'technology_ids' => [$tech->id]];
        $response = $this->browserRequest('POST', '/api/v1/projects', $body, ['Idempotency-Key' => self::KEY])->assertCreated()
            ->assertJsonPath('data.owner.id', $owner->id)->assertJsonPath('data.name', 'Projet pédagogique')
            ->assertJsonPath('data.publication_state', 'draft')->assertJsonPath('data.project_stage', 'prototype')
            ->assertJsonPath('data.lock_version', 1)->assertJsonPath('data.published_at', null)->assertJsonPath('data.description', null)
            ->assertJsonCount(1, 'data.technologies')->assertHeader('Cache-Control', 'no-store, private');
        $body['name'] = 'Projet pédagogique';
        $body['technology_ids'] = [strtoupper($tech->id)];
        $this->browserRequest('POST', '/api/v1/projects', $body, ['Idempotency-Key' => strtoupper(self::KEY)])
            ->assertCreated()->assertExactJson($response->json());
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseCount('project_technologies', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->assertDatabaseCount('content_revisions', 1);
        $schema = Yaml::parseFile(base_path('../docs/api/openapi/projects.yaml'))['components']['schemas']['Project'];
        $this->assertEqualsCanonicalizing($schema['required'], array_keys($response->json('data')));
        $audit = DB::table('content_revisions')->sole();
        $this->assertSame('project.created', $audit->action);
        $this->assertSame($owner->id, $audit->actor_id);
        $stored = json_encode([$audit, DB::table('api_idempotency')->sole()], JSON_THROW_ON_ERROR);
        foreach ([$body['name'], $body['demo_url'], $owner->email, self::KEY] as $private) {
            $this->assertStringNotContainsString($private, $stored);
        }
        foreach (['email', 'status', 'role', 'hidden_at', 'moderation_reason', 'owner_id'] as $privateField) {
            $this->assertArrayNotHasKey($privateField, $response->json('data'));
            $this->assertArrayNotHasKey($privateField, $response->json('data.owner'));
        }
        Http::assertNothingSent();
        $this->browserRequest('POST', '/api/v1/projects', array_replace($body, ['name' => 'Une autre intention']), ['Idempotency-Key' => self::KEY])
            ->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }

    public function test_minimal_draft_does_not_invent_text_or_publication(): void
    {
        $owner = User::factory()->verified()->create();
        $this->login($owner);
        $this->browserRequest('POST', '/api/v1/projects', ['project_stage' => 'idea'], ['Idempotency-Key' => self::KEY])->assertCreated()
            ->assertJsonPath('data.name', null)->assertJsonPath('data.summary', null)->assertJsonPath('data.description', null)
            ->assertJsonPath('data.published_at', null)->assertJsonCount(0, 'data.technologies');
        $this->assertSame(ProjectPublicationState::Draft, Project::sole()->publication_state);
    }

    public function test_edit_and_replay_keep_owner_and_publication_separate_with_atomic_audit(): void
    {
        $this->freezeTime();
        $owner = User::factory()->verified()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $technology = Technology::factory()->create();
        $this->login($owner);
        $body = ['lock_version' => 1, 'name' => 'Projet corrigé', 'project_stage' => 'launched', 'technology_ids' => [$technology->id]];
        $first = $this->browserRequest('PATCH', '/api/v1/projects/'.$project->id, $body, ['Idempotency-Key' => self::KEY])
            ->assertOk()->assertJsonPath('data.lock_version', 2)->assertJsonPath('data.name', 'Projet corrigé')
            ->assertJsonPath('data.publication_state', 'draft')->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.project_stage', 'launched')->assertJsonPath('data.owner.id', $owner->id);
        $this->browserRequest('PATCH', '/api/v1/projects/'.strtoupper($project->id), $body, ['Idempotency-Key' => self::KEY])
            ->assertOk()->assertExactJson($first->json());
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->assertDatabaseCount('content_revisions', 1);
        $audit = DB::table('content_revisions')->sole();
        $this->assertSame('project.updated', $audit->action);
        $this->assertEquals(['project_version' => 2, 'changed_fields' => ['name', 'project_stage', 'technology_ids'], 'technology_count' => 1], json_decode($audit->metadata, true));
        $this->browserRequest('PATCH', '/api/v1/projects/'.$project->id, ['lock_version' => 1, 'name' => 'Projet obsolète'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(409)->assertJsonPath('error.code', 'RESOURCE_CONFLICT');
        $this->browserRequest('PATCH', '/api/v1/projects/'.$project->id, ['lock_version' => 2, 'help_sought' => null, 'technology_ids' => []], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.lock_version', 3);
        $this->browserRequest('PATCH', '/api/v1/projects/'.$project->id, $body, ['Idempotency-Key' => self::KEY])->assertStatus(409);
        $this->assertDatabaseCount('api_idempotency', 2);
        $this->assertDatabaseCount('content_revisions', 2);
    }

    #[DataProvider('otherActors')]
    public function test_a_third_party_or_admin_cannot_edit_a_private_or_public_project(bool $admin, bool $published): void
    {
        $actor = $admin ? User::factory()->verified()->admin()->create() : User::factory()->verified()->create();
        $project = $published ? Project::factory()->published()->create() : Project::factory()->create();
        $this->login($actor);
        $this->browserRequest('PATCH', '/api/v1/projects/'.$project->id, ['lock_version' => 1, 'name' => 'Modification interdite'], ['Idempotency-Key' => self::KEY])
            ->assertStatus($published ? 403 : 404);
        $this->assertSame(1, $project->refresh()->lock_version);
        $this->assertDatabaseCount('content_revisions', 0);
        $this->assertDatabaseCount('api_idempotency', 0);
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function otherActors(): iterable
    {
        yield 'member private' => [false, false];
        yield 'admin private' => [true, false];
        yield 'member public' => [false, true];
        yield 'admin public' => [true, true];
    }

    #[DataProvider('blockedStates')]
    public function test_archived_or_hidden_project_is_not_editable(string $state): void
    {
        $owner = User::factory()->verified()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id,
            ...($state === 'hidden' ? ['hidden_at' => now()] : ['publication_state' => 'archived'])]);
        $this->login($owner);
        $this->browserRequest('PATCH', '/api/v1/projects/'.$project->id, ['lock_version' => 1, 'name' => 'Modification interdite'], ['Idempotency-Key' => self::KEY])
            ->assertStatus($state === 'hidden' ? 404 : 403);
        $this->assertSame(1, $project->refresh()->lock_version);
    }

    /** @return iterable<string, array{string}> */
    public static function blockedStates(): iterable
    {
        yield 'archived' => ['archived'];
        yield 'hidden' => ['hidden'];
    }

    public function test_published_content_is_validated_as_a_whole_without_implicit_unpublication(): void
    {
        $owner = User::factory()->verified()->create();
        $project = Project::factory()->published()->create(['owner_id' => $owner->id]);
        $project->technologies()->attach(Technology::factory()->create()->id);
        $this->login($owner);
        $this->browserRequest('PATCH', '/api/v1/projects/'.$project->id, ['lock_version' => 1, 'description' => null], ['Idempotency-Key' => self::KEY])->assertUnprocessable();
        $this->browserRequest('PATCH', '/api/v1/projects/'.$project->id, ['lock_version' => 1, 'technology_ids' => []], ['Idempotency-Key' => self::KEY])->assertUnprocessable();
        $this->assertSame(ProjectPublicationState::Published, $project->refresh()->publication_state);
        $this->assertSame(1, $project->lock_version);
        $this->assertDatabaseCount('api_idempotency', 0);
        $this->assertDatabaseCount('content_revisions', 0);
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('badCreation')]
    public function test_json_input_is_strict_and_no_protected_field_is_accepted(array $body): void
    {
        $this->login(User::factory()->verified()->create());
        $this->browserRequest('POST', '/api/v1/projects', $body, ['Idempotency-Key' => self::KEY])->assertUnprocessable();
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('api_idempotency', 0);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function badCreation(): iterable
    {
        foreach (['owner_id', 'id', 'publication_state', 'published_at', 'hidden_at', 'moderation_reason', 'lock_version', 'unknown'] as $field) {
            yield $field => [['name' => 'Projet fictif', $field => 'client']];
        }
        yield 'raw control' => [['name' => "\0Projet fictif"]];
        yield 'format-only name' => [['name' => "\u{200b}\u{200d}\u{00a0}"]];
        yield 'unknown stage' => [['project_stage' => 'certified']];
        yield 'http' => [['demo_url' => 'http://example.test/project']];
        yield 'userinfo' => [['repository_url' => 'https://member:fictional@example.test/project']];
        yield 'javascript' => [['demo_url' => 'javascript:alert(1)']];
        yield 'missing technology' => [['technology_ids' => ['f0000000-1111-4111-8111-111111111111']]];
        yield 'technology associative' => [['technology_ids' => ['unknown' => 'f0000000-1111-4111-8111-111111111111']]];
    }

    #[DataProvider('invalidVersions')]
    public function test_lock_version_is_a_bounded_json_integer(mixed $version): void
    {
        $owner = User::factory()->verified()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $this->login($owner);
        $this->browserRequest('PATCH', '/api/v1/projects/'.$project->id, ['lock_version' => $version, 'name' => 'Projet édité'], ['Idempotency-Key' => self::KEY])->assertUnprocessable();
        $this->assertSame(1, $project->refresh()->lock_version);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidVersions(): iterable
    {
        yield 'string' => ['1'];
        yield 'float' => [1.5];
        yield 'bool' => [true];
        yield 'zero' => [0];
        yield 'maximum' => [2147483647];
        yield 'overflow' => [2147483648];
    }

    public function test_query_parameters_missing_key_and_csrf_are_rejected_without_writes(): void
    {
        $this->login(User::factory()->verified()->create());
        $this->browserRequest('POST', '/api/v1/projects?name=Projet', ['project_stage' => 'idea'], ['Idempotency-Key' => self::KEY])->assertUnprocessable();
        $this->browserRequest('POST', '/api/v1/projects', ['project_stage' => 'idea'])->assertUnprocessable();
        $this->browserRequest('POST', '/api/v1/projects', ['project_stage' => 'idea'], ['Idempotency-Key' => self::KEY], false)->assertStatus(419);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_integral_float_in_original_json_is_not_a_version_integer(): void
    {
        $owner = User::factory()->verified()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $this->login($owner);
        Auth::forgetGuards();
        app()->forgetInstance('auth.driver');
        Auth::shouldUse('web');
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        $headers = ['Accept' => 'application/json', 'Origin' => 'https://app.haas.example.com', 'X-XSRF-TOKEN' => $this->browserCookies['XSRF-TOKEN'], 'Idempotency-Key' => self::KEY];
        $this->call('PATCH', 'https://api.haas.example.com/api/v1/projects/'.$project->id, [], $this->browserCookies, [],
            [...$this->transformHeadersToServerVars($headers), 'CONTENT_TYPE' => 'application/json'],
            '{"lock_version":1.0,"name":"Projet fractionnaire"}')->assertUnprocessable()
            ->assertJsonStructure(['error' => ['fields' => ['lock_version']]])->assertJsonMissingPath('error.fields.Idempotency-Key');
        $this->assertSame(1, $project->refresh()->lock_version);
        $this->assertDatabaseCount('api_idempotency', 0);
    }

    public function test_replay_rechecks_current_rights_visibility_and_target_version(): void
    {
        $owner = User::factory()->verified()->create();
        $this->login($owner);
        $this->browserRequest('POST', '/api/v1/projects', ['name' => 'Projet fictif'], ['Idempotency-Key' => self::KEY])->assertCreated();
        $project = Project::sole();
        $project->hidden_at = CarbonImmutable::now();
        $project->save();
        $this->browserRequest('POST', '/api/v1/projects', ['name' => 'Projet fictif'], ['Idempotency-Key' => self::KEY])->assertForbidden();
        $project->hidden_at = null;
        $project->lock_version = 2;
        $project->save();
        $this->browserRequest('POST', '/api/v1/projects', ['name' => 'Projet fictif'], ['Idempotency-Key' => self::KEY])->assertStatus(409);
        $owner->status = AccountStatus::Suspended;
        $owner->save();
        $this->browserRequest('POST', '/api/v1/projects', ['name' => 'Projet fictif'], ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseCount('content_revisions', 1);
    }

    public function test_direct_service_rejects_a_stale_owner_even_if_the_controller_was_bypassed(): void
    {
        $stale = User::factory()->verified()->create();
        User::whereKey($stale->id)->update(['status' => 'suspended']);
        $this->expectException(AuthorizationException::class);
        app(CreateProjectService::class)->create($stale, new CreateProjectData(['name' => 'Projet fictif']), new IdempotencyKey(self::KEY));
    }

    public function test_storage_failure_rolls_back_project_technologies_audit_and_intention(): void
    {
        $owner = User::factory()->verified()->create();
        $this->login($owner);
        $technology = Technology::factory()->create();
        DB::unprepared("CREATE FUNCTION bc02_fail_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'BC02 fixture storage unavailable'; END $$");
        DB::unprepared('CREATE TRIGGER bc02_fail_audit BEFORE INSERT ON content_revisions FOR EACH ROW EXECUTE FUNCTION bc02_fail_audit()');
        try {
            $this->browserRequest('POST', '/api/v1/projects', ['name' => 'Projet fictif', 'technology_ids' => [$technology->id]], ['Idempotency-Key' => self::KEY])
                ->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR');
            $this->assertDatabaseCount('projects', 0);
            $this->assertDatabaseCount('project_technologies', 0);
            $this->assertDatabaseCount('content_revisions', 0);
            $this->assertDatabaseCount('api_idempotency', 0);
        } finally {
            DB::unprepared('DROP TRIGGER bc02_fail_audit ON content_revisions');
            DB::unprepared('DROP FUNCTION bc02_fail_audit()');
        }
    }

    private function login(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}
