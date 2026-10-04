<?php

namespace Tests\Integration\HelpRequests;

use App\Enums\HelpRequests\HelpIntent;
use App\Enums\Identity\AccountStatus;
use App\Models\HelpRequest;
use App\Models\Technology;
use App\Models\User;
use App\Policies\HelpRequestPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class CreateHelpRequestTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    private const string KEY = 'fda9a000-3333-4444-8888-123456789abc';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_publication_is_idempotent_with_atomic_audit_and_no_private_snapshot(): void
    {
        $this->freezeTime();
        $owner = User::factory()->verified()->create();
        $this->login($owner);
        $body = $this->body();
        $body['code'] = "  <script>alert('texte seulement')</script>\n  ";
        $body['code_language'] = 'html';
        $first = $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => self::KEY])->assertCreated()
            ->assertJsonPath('data.state', 'open')->assertJsonPath('data.author.id', $owner->id)
            ->assertJsonPath('data.code', $body['code'])->assertJsonPath('data.lock_version', 1)
            ->assertHeader('Cache-Control', 'no-store, private');
        $body['technologies'] = array_reverse($body['technologies']);
        foreach ($body['technologies'] as &$technology) {
            $technology['id'] = strtoupper($technology['id']);
        }
        unset($technology);
        $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => strtoupper(self::KEY)])
            ->assertCreated()->assertExactJson($first->json());
        $this->assertDatabaseCount('help_requests', 1);
        $this->assertDatabaseCount('request_technologies', 2);
        $this->assertDatabaseCount('content_revisions', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
        $schema = Yaml::parseFile(base_path('../docs/api/openapi/community.yaml'))['components']['schemas']['HelpRequest'];
        $this->assertEqualsCanonicalizing($schema['required'], array_keys($first->json('data')));
        $audit = DB::table('content_revisions')->sole();
        $this->assertSame('help_request.created', $audit->action);
        $this->assertSame($owner->id, $audit->actor_id);
        $this->assertEquals(['state' => 'open', 'help_intent' => 'unblock', 'request_version' => 1, 'technology_count' => 2], json_decode($audit->metadata, true));
        $recorded = json_encode([DB::table('api_idempotency')->sole(), $audit], JSON_THROW_ON_ERROR);
        foreach ([$body['title'], $body['code'], $owner->email, self::KEY] as $private) {
            $this->assertStringNotContainsString($private, $recorded);
        }
        foreach (['email', 'password', 'role', 'status', 'hidden_at'] as $privateField) {
            $this->assertArrayNotHasKey($privateField, $first->json('data.author'));
        }
        $this->browserRequest('POST', '/api/v1/requests', array_replace($body, ['title' => 'Une autre intention de création']), ['Idempotency-Key' => self::KEY])
            ->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertDatabaseCount('help_requests', 1);
    }

    public function test_minimal_draft_is_private_even_from_an_admin_and_never_filled_with_fake_content(): void
    {
        $owner = User::factory()->verified()->create();
        $admin = User::factory()->verified()->admin()->create();
        $this->login($owner);
        $this->browserRequest('POST', '/api/v1/requests', ['mode' => 'draft', 'help_intent' => 'unblock', 'title' => 'À préciser'], ['Idempotency-Key' => self::KEY])
            ->assertCreated()->assertJsonPath('data.state', 'draft')->assertJsonPath('data.goal', null)->assertJsonCount(0, 'data.technologies');
        $draft = HelpRequest::sole();
        $policy = app(HelpRequestPolicy::class);
        $this->assertTrue(Gate::forUser($owner)->allows('view', $draft));
        $this->assertFalse(Gate::forUser($admin)->allows('view', $draft));
        $this->assertFalse($policy->view(null, $draft));
        $this->assertNull($draft->expected);
        $this->assertNull($draft->attempts);
    }

    public function test_question_publishes_without_code_expected_attempts_or_environment(): void
    {
        $owner = User::factory()->verified()->create();
        $this->login($owner);
        $body = $this->body();
        $body['help_intent'] = 'ask_question';
        unset($body['expected'], $body['attempts'], $body['environment']);
        $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => self::KEY])->assertCreated()
            ->assertJsonPath('data.help_intent', 'ask_question')->assertJsonPath('data.expected', null)
            ->assertJsonPath('data.attempts', null)->assertJsonPath('data.code', null);
        $question = HelpRequest::sole();
        $this->assertSame(HelpIntent::AskQuestion, $question->help_intent);
        $this->assertTrue(app(HelpRequestPolicy::class)->view(null, $question));
    }

    public function test_the_shared_intent_covers_solution_review_and_reproduction_without_a_second_workflow(): void
    {
        $this->login(User::factory()->verified()->create());
        $body = $this->body();
        foreach (['review_solution', 'reproduce_behavior'] as $intent) {
            $this->browserRequest('POST', '/api/v1/requests', array_replace($body, ['help_intent' => $intent]), ['Idempotency-Key' => (string) Str::uuid()])
                ->assertCreated()->assertJsonPath('data.help_intent', $intent)->assertJsonPath('data.state', 'open');
        }
        $contract = Yaml::parseFile(base_path('../docs/api/openapi/community.yaml'));
        $this->assertSame(array_column(HelpIntent::cases(), 'value'), $contract['components']['schemas']['HelpIntent']['enum']);
        $this->assertDatabaseCount('help_requests', 2);
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidContent')]
    public function test_invalid_content_has_field_errors_and_no_side_effect(array $changes, string $field): void
    {
        $owner = User::factory()->verified()->create();
        $this->login($owner);
        $this->browserRequest('POST', '/api/v1/requests', array_replace($this->body(), $changes), ['Idempotency-Key' => self::KEY])
            ->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
        $this->assertDatabaseCount('help_requests', 0);
        $this->assertDatabaseCount('api_idempotency', 0);
        $this->assertDatabaseCount('content_revisions', 0);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidContent(): iterable
    {
        foreach (['mode' => 'open', 'help_intent' => 'chat', 'title' => str_repeat('é', 14), 'goal' => null,
            'expected' => null, 'observed' => str_repeat('x', 29), 'attempts' => 'pas encore', 'environment' => null,
            'technologies' => [], 'code_language' => '<script>', 'primary_language' => 'fr<script>',
            'reproduction_url' => 'http://example.test/demo'] as $field => $value) {
            yield $field => [[$field => $value], $field];
        }
        foreach (['title' => 140, 'goal' => 2000, 'expected' => 2000, 'observed' => 4000, 'attempts' => 3000, 'environment' => 255, 'code' => 12000] as $field => $max) {
            yield 'oversize '.$field => [[$field => str_repeat('é', $max + 1), 'code_language' => 'text'], $field];
        }
        yield 'missing code language' => [['code' => '<p>Texte inerte</p>'], 'code_language'];
        yield 'unknown technology' => [['technologies' => [['id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee']]], 'technologies.0.id'];
        yield 'protected pivot' => [['technologies' => [['id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'author_id' => null]]], 'technologies.0'];
        yield 'secret in title' => [['title' => 'Copie de PRIVATE KEY à retirer'], 'title'];
        yield 'secret in code' => [['code' => 'API_TOKEN="valeurfictive12345678"', 'code_language' => 'text'], 'code'];
        yield 'credentials in URL' => [['reproduction_url' => 'https://user:pass@example.test/demo'], 'reproduction_url'];
    }

    public function test_server_fields_are_rejected_even_when_null(): void
    {
        $owner = User::factory()->verified()->create();
        $this->login($owner);
        $body = $this->body();
        foreach (['id', 'author_id', 'state', 'lock_version', 'hidden_at', 'created_at', 'updated_at', 'accepted_by', 'project_id'] as $field) {
            $this->browserRequest('POST', '/api/v1/requests', $body + [$field => null], ['Idempotency-Key' => self::KEY])
                ->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
        }
        $this->assertDatabaseCount('help_requests', 0);
    }

    public function test_csrf_and_required_key_protect_creation(): void
    {
        $this->login(User::factory()->verified()->create());
        $body = $this->body();
        $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => self::KEY], sendXsrf: false)->assertStatus(419);
        foreach ([[], ['Idempotency-Key' => 'invalid'], ['Idempotency-Key' => '']] as $headers) {
            $this->browserRequest('POST', '/api/v1/requests', $body, $headers)->assertUnprocessable()->assertJsonValidationErrors('Idempotency-Key', 'error.fields');
        }
        $this->assertDatabaseCount('help_requests', 0);
    }

    public function test_anonymous_and_unverified_members_cannot_create_even_drafts(): void
    {
        $body = ['mode' => 'draft', 'help_intent' => 'unblock', 'title' => 'Brouillon'];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => self::KEY])->assertUnauthorized();
        $this->login(User::factory()->create());
        $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertDatabaseCount('help_requests', 0);
    }

    public function test_replay_rechecks_hidden_content_version_and_account_status(): void
    {
        $owner = User::factory()->verified()->create();
        $this->login($owner);
        $body = $this->body();
        $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => self::KEY])->assertCreated();
        HelpRequest::query()->update(['hidden_at' => now()]);
        $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => self::KEY])->assertForbidden()->assertJsonMissingPath('data');
        HelpRequest::query()->update(['hidden_at' => null, 'lock_version' => 2]);
        $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => self::KEY])->assertStatus(409)->assertJsonMissingPath('data');
        $owner->forceFill(['status' => AccountStatus::Suspended])->save();
        $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => self::KEY])->assertForbidden()->assertJsonMissingPath('data');
        $this->assertDatabaseCount('help_requests', 1);
        $this->assertDatabaseCount('content_revisions', 1);
    }

    public function test_failed_audit_rolls_back_content_pivot_and_intention_then_retry_succeeds(): void
    {
        $this->login(User::factory()->verified()->create());
        $body = $this->body();
        DB::statement('ALTER TABLE content_revisions ADD CONSTRAINT fixture_fail_b14 CHECK (false)');
        $response = $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => self::KEY])->assertStatus(500);
        $responseContent = $response->getContent();
        $this->assertIsString($responseContent);
        $this->assertStringNotContainsString($body['title'], $responseContent);
        foreach (['help_requests', 'request_technologies', 'content_revisions', 'api_idempotency'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        DB::statement('ALTER TABLE content_revisions DROP CONSTRAINT fixture_fail_b14');
        $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => self::KEY])->assertCreated();
    }

    public function test_unicode_at_all_content_limits_does_not_overflow_the_idempotency_payload(): void
    {
        $this->login(User::factory()->verified()->create());
        $body = $this->body();
        foreach (['title' => 140, 'goal' => 2000, 'expected' => 2000, 'observed' => 4000, 'attempts' => 3000, 'environment' => 255, 'code' => 12000] as $field => $max) {
            $body[$field] = str_repeat('😀', $max);
        }
        $body['code_language'] = 'text';
        $this->browserRequest('POST', '/api/v1/requests', $body, ['Idempotency-Key' => self::KEY])->assertCreated()->assertJsonPath('data.code', $body['code']);
    }

    public function test_technology_count_versions_and_duplicates_are_bounded(): void
    {
        $this->login(User::factory()->verified()->create());
        $body = $this->body();
        $id = $body['technologies'][0]['id'];
        foreach ([
            [[['id' => $id], ['id' => strtoupper($id)]], 'technologies.0.id'],
            [[['id' => $id, 'version_label' => str_repeat('x', 41)]], 'technologies.0.version_label'],
            [array_map(fn ($tech) => ['id' => $tech->id], Technology::factory()->count(6)->create()->all()), 'technologies'],
        ] as [$technologies, $field]) {
            $this->browserRequest('POST', '/api/v1/requests', array_replace($body, ['technologies' => $technologies]), ['Idempotency-Key' => self::KEY])
                ->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
        }
        $this->assertDatabaseCount('help_requests', 0);
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        return ['mode' => 'publish', 'help_intent' => 'unblock', 'title' => 'Comprendre une erreur de traitement',
            'goal' => 'Comprendre pourquoi la file ne termine pas le traitement.', 'expected' => 'Obtenir un résultat stable et documenté pour cette entrée.',
            'observed' => 'Le résultat reste incomplet lorsque le traitement se termine.', 'attempts' => 'aucune', 'environment' => 'PHP 8.5 / PostgreSQL 17',
            'technologies' => Technology::factory()->count(2)->create()->map(fn (Technology $tech): array => ['id' => $tech->id, 'version_label' => '1.x'])->all()];
    }

    private function login(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}
