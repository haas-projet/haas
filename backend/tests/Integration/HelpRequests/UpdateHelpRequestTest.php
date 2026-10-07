<?php

namespace Tests\Integration\HelpRequests;

use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\Comment;
use App\Models\HelpRequest;
use App\Models\HelpRequestRevision;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class UpdateHelpRequestTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    private const string KEY = 'fda9a000-3333-4444-8888-123456789abc';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_partial_edit_is_versioned_idempotent_and_preserves_untouched_fields(): void
    {
        $item = $this->requestFixture('open');
        $body = ['lock_version' => 1, 'title' => 'Clarification de la demande initiale'];
        $first = $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, $body, ['Idempotency-Key' => self::KEY])->assertOk()
            ->assertJsonPath('data.lock_version', 2)->assertJsonPath('data.goal', $item->goal)->assertJsonPath('data.state', 'open');
        $this->browserRequest('PATCH', '/api/v1/requests/'.strtoupper($item->id), $body, ['Idempotency-Key' => strtoupper(self::KEY)])->assertOk()->assertExactJson($first->json());
        $revision = HelpRequestRevision::sole();
        $this->assertSame(['title'], $revision->changed_fields);
        $this->assertSame(2, $revision->request_version);
        $this->assertSame($item->author_id, $revision->actor_id);
        $this->assertTrue($revision->is_public);
        $this->assertDatabaseCount('content_revisions', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, array_replace($body, ['title' => 'Autre contenu même clé']), ['Idempotency-Key' => self::KEY])
            ->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, $body, ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
        $this->assertSame(2, $item->refresh()->lock_version);
        $this->assertDatabaseCount('help_request_revisions', 1);
    }

    public function test_technologies_are_replaced_and_order_is_normalized_for_replay(): void
    {
        $item = $this->requestFixture('open');
        $techs = Technology::factory()->count(2)->create()->map(fn (Technology $tech): array => ['id' => $tech->id, 'version_label' => '17'])->all();
        $body = ['lock_version' => 1, 'technologies' => $techs];
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, $body, ['Idempotency-Key' => self::KEY])->assertOk()->assertJsonCount(2, 'data.technologies');
        $body['technologies'] = array_reverse($techs);
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, $body, ['Idempotency-Key' => self::KEY])->assertOk()->assertJsonPath('data.lock_version', 2);
        $this->assertSame(['technologies'], HelpRequestRevision::sole()->changed_fields);
        $this->assertEqualsCanonicalizing(array_column($techs, 'id'), $item->technologies()->pluck('technologies.id')->all());
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 2, 'technologies' => []], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(2, $item->refresh()->lock_version);
    }

    #[DataProvider('roles')]
    public function test_non_owner_has_no_edit_or_publish_right_even_with_privileged_role(Role $role): void
    {
        $item = $this->requestFixture('open');
        $owner = $item->author;
        $this->assertInstanceOf(User::class, $owner);
        $draft = HelpRequest::factory()->for($owner, 'author')->create();
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->login(User::factory()->verified()->state(['role' => $role])->create());
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 1, 'title' => 'Une modification interdite'], ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->browserRequest('POST', '/api/v1/requests/'.$draft->id.'/publish', ['lock_version' => 1], ['Idempotency-Key' => self::KEY])->assertNotFound();
        $this->browserRequest('GET', '/api/v1/requests/'.$draft->id.'/revisions')->assertNotFound();
        $this->assertDatabaseCount('help_request_revisions', 0);
        $this->assertDatabaseCount('api_idempotency', 0);
    }

    /** @return iterable<string, array{Role}> */
    public static function roles(): iterable
    {
        foreach (Role::cases() as $role) {
            yield $role->value => [$role];
        }
    }

    #[DataProvider('contributions')]
    public function test_note_is_required_after_a_real_contribution_and_never_copied_to_audit(string $kind): void
    {
        $item = $this->requestFixture('open');
        if ($kind === 'comment') {
            Comment::factory()->create(['request_id' => $item->id, 'hidden_at' => now()]);
        } else {
            Proposal::factory()->create(['request_id' => $item->id]);
        }
        $body = ['lock_version' => 1, 'title' => 'Clarification après contribution'];
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, $body, ['Idempotency-Key' => self::KEY])->assertUnprocessable()->assertJsonValidationErrors('edit_note', 'error.fields');
        $this->assertSame(1, $item->refresh()->lock_version);
        $body['edit_note'] = 'Je précise le contexte sans remplacer le problème initial.';
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, $body, ['Idempotency-Key' => self::KEY])->assertOk();
        $revision = HelpRequestRevision::sole();
        $this->assertSame($body['edit_note'], $revision->edit_note);
        $audit = DB::table('content_revisions')->sole();
        $this->assertEquals(['changed_fields' => ['title'], 'request_version' => 2, 'has_note' => true], json_decode($audit->metadata, true));
        $this->assertStringNotContainsString($body['edit_note'], $audit->metadata);
        $this->assertStringNotContainsString($body['title'], $audit->metadata);
        $this->assertDatabaseCount($kind === 'comment' ? 'comments' : 'proposals', 1);
    }

    /** @return iterable<string, array{string}> */
    public static function contributions(): iterable
    {
        yield 'hidden comment' => ['comment'];
        yield 'proposal' => ['proposal'];
    }

    public function test_final_content_is_validated_for_question_and_technical_intents(): void
    {
        $item = $this->requestFixture('open');
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 1, 'expected' => null], ['Idempotency-Key' => self::KEY])->assertUnprocessable()->assertJsonValidationErrors('expected', 'error.fields');
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 1, 'help_intent' => 'ask_question', 'expected' => null, 'attempts' => null, 'environment' => null], ['Idempotency-Key' => self::KEY])->assertOk()->assertJsonPath('data.help_intent', 'ask_question');
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 2, 'help_intent' => 'unblock'], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors(['expected', 'attempts', 'environment'], 'error.fields');
        $this->assertSame(2, $item->refresh()->lock_version);
    }

    public function test_draft_is_not_published_by_edit_and_publish_validates_current_content(): void
    {
        $item = $this->requestFixture();
        $item->forceFill(['goal' => null])->save();
        $this->browserRequest('POST', '/api/v1/requests/'.$item->id.'/publish', ['lock_version' => 1], ['Idempotency-Key' => self::KEY])->assertUnprocessable()->assertJsonValidationErrors('goal', 'error.fields');
        $body = ['lock_version' => 1, 'goal' => 'Un contexte complet pour publier cette demande.'];
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, $body, ['Idempotency-Key' => self::KEY])->assertOk()->assertJsonPath('data.state', 'draft');
        $first = $this->browserRequest('POST', '/api/v1/requests/'.$item->id.'/publish', ['lock_version' => 2], ['Idempotency-Key' => self::KEY])->assertOk()->assertJsonPath('data.state', 'open')->assertJsonPath('data.lock_version', 3);
        $this->browserRequest('POST', '/api/v1/requests/'.$item->id.'/publish', ['lock_version' => 2], ['Idempotency-Key' => self::KEY])->assertOk()->assertExactJson($first->json());
        $this->browserRequest('POST', '/api/v1/requests/'.$item->id.'/publish', ['lock_version' => 3], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
        $this->assertSame(['state'], HelpRequestRevision::where('action', 'published')->sole()->changed_fields);
        $this->assertDatabaseCount('help_request_revisions', 2);
    }

    public function test_b14_creation_then_edit_and_publication_keep_one_consistent_audit_sequence(): void
    {
        $this->login(User::factory()->verified()->create());
        $draft = ['mode' => 'draft', 'help_intent' => 'ask_question', 'title' => 'Question'];
        $created = $this->browserRequest('POST', '/api/v1/requests', $draft, ['Idempotency-Key' => self::KEY])->assertCreated();
        $id = $created->json('data.id');
        $tech = Technology::factory()->create();
        $this->browserRequest('PATCH', '/api/v1/requests/'.$id, ['lock_version' => 1, 'title' => 'Question sur le fonctionnement du système',
            'goal' => 'Comprendre le contexte complet de cette question.', 'observed' => 'Comment choisir une approche adaptée à ce contexte ?', 'technologies' => [['id' => $tech->id]]], ['Idempotency-Key' => self::KEY])->assertOk()->assertJsonPath('data.lock_version', 2);
        $this->browserRequest('POST', '/api/v1/requests/'.$id.'/publish', ['lock_version' => 2], ['Idempotency-Key' => self::KEY])->assertOk()->assertJsonPath('data.lock_version', 3)->assertJsonPath('data.state', 'open');
        $this->assertSame([1, 2, 3], DB::table('content_revisions')->where('resource_id', $id)->orderBy('revision')->pluck('revision')->all());
        $this->assertSame(['help_request.created', 'help_request.updated', 'help_request.published'], DB::table('content_revisions')->where('resource_id', $id)->orderBy('revision')->pluck('action')->all());
        $this->browserRequest('POST', '/api/v1/requests', $draft, ['Idempotency-Key' => self::KEY])->assertStatus(409);
        $this->assertDatabaseCount('help_requests', 1);
        $this->assertDatabaseCount('api_idempotency', 3);
    }

    public function test_draft_notes_stay_private_after_publication_and_parent_controls_every_history_read(): void
    {
        $item = $this->requestFixture();
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 1, 'title' => 'Contexte clarifié avant publication', 'edit_note' => 'Note privée de préparation du brouillon.'], ['Idempotency-Key' => self::KEY])->assertOk();
        $this->browserRequest('POST', '/api/v1/requests/'.$item->id.'/publish', ['lock_version' => 2], ['Idempotency-Key' => self::KEY])->assertOk();
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 3, 'title' => 'Contexte clarifié après publication', 'edit_note' => 'Note publique de clarification après publication.'], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
        $this->browserRequest('GET', '/api/v1/requests/'.$item->id.'/revisions')->assertOk()->assertJsonPath('meta.total', 3);
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $history = $this->browserRequest('GET', '/api/v1/requests/'.$item->id.'/revisions?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.request_version', 4)->assertHeader('Cache-Control', 'no-store, private');
        $schema = Yaml::parseFile(base_path('../docs/api/openapi/community.yaml'))['components']['schemas']['HelpRequestRevision'];
        $this->assertEqualsCanonicalizing($schema['required'], array_keys($history->json('data.0')));
        $this->browserRequest('GET', '/api/v1/requests/'.$item->id.'/revisions?page=2&per_page=1')->assertOk()->assertJsonPath('data.0.request_version', 3)->assertJsonPath('data.0.edit_note', null);
        $this->browserRequest('GET', '/api/v1/requests/'.$item->id.'/revisions?page=3&per_page=1')->assertOk()->assertJsonPath('data', []);
        $this->browserRequest('GET', '/api/v1/requests/'.$item->id.'/revisions?is_public=false')->assertUnprocessable();
        $item->forceFill(['hidden_at' => now()])->save();
        $this->browserRequest('GET', '/api/v1/requests/'.$item->id.'/revisions')->assertNotFound();
        $item->forceFill(['hidden_at' => null])->save();
        $owner = $item->author;
        $this->assertInstanceOf(User::class, $owner);
        $owner->forceFill(['status' => AccountStatus::Suspended])->save();
        $this->browserRequest('GET', '/api/v1/requests/'.$item->id.'/revisions')->assertNotFound();
    }

    public function test_replay_cannot_expose_old_version_or_hidden_content(): void
    {
        $item = $this->requestFixture();
        $body = ['lock_version' => 1, 'title' => 'Première modification'];
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, $body, ['Idempotency-Key' => self::KEY])->assertOk();
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 2, 'title' => 'Deuxième modification'], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, $body, ['Idempotency-Key' => self::KEY])->assertStatus(409);
        $item->forceFill(['hidden_at' => now()])->save();
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, $body, ['Idempotency-Key' => self::KEY])->assertNotFound();
        $this->assertDatabaseCount('help_request_revisions', 2);
    }

    #[DataProvider('storageFailures')]
    public function test_failed_history_or_audit_rolls_back_content_pivot_version_and_intention(string $table): void
    {
        $item = $this->requestFixture();
        $oldTech = $item->technologies()->sole()->id;
        $tech = Technology::factory()->create();
        $body = ['lock_version' => 1, 'title' => 'Texte à ne pas exposer dans une panne', 'technologies' => [['id' => $tech->id]]];
        DB::statement('ALTER TABLE '.$table.' ADD CONSTRAINT fixture_fail_b16 CHECK (false)');
        $response = $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, $body, ['Idempotency-Key' => self::KEY])->assertStatus(500);
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString($body['title'], $content);
        $this->assertSame(1, $item->refresh()->lock_version);
        $this->assertSame($oldTech, $item->technologies()->sole()->id);
        foreach (['help_request_revisions', 'content_revisions', 'api_idempotency'] as $storage) {
            $this->assertDatabaseCount($storage, 0);
        }
        DB::statement('ALTER TABLE '.$table.' DROP CONSTRAINT fixture_fail_b16');
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, $body, ['Idempotency-Key' => self::KEY])->assertOk();
    }

    /** @return iterable<string, array{string}> */
    public static function storageFailures(): iterable
    {
        yield 'domain history' => ['help_request_revisions'];
        yield 'technical audit' => ['content_revisions'];
    }

    public function test_protected_fields_and_publish_payload_are_rejected_even_when_null(): void
    {
        $item = $this->requestFixture();
        foreach (['mode', 'state', 'author_id', 'hidden_at', 'created_at', 'resolution_id', 'project_id', 'request_version', 'is_public', 'technologies.*.id'] as $field) {
            $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 1, 'title' => 'Brouillon', $field => null], ['Idempotency-Key' => self::KEY])->assertUnprocessable();
        }
        $this->browserRequest('POST', '/api/v1/requests/'.$item->id.'/publish', ['lock_version' => 1, 'title' => null], ['Idempotency-Key' => self::KEY])->assertUnprocessable()->assertJsonValidationErrors('title', 'error.fields');
        $this->assertDatabaseCount('help_request_revisions', 0);
    }

    /** @param array<string, mixed> $change */
    #[DataProvider('invalidChanges')]
    public function test_invalid_partial_edits_have_no_effect(array $change, string $field): void
    {
        $item = $this->requestFixture('open');
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, array_replace(['lock_version' => 1], $change), ['Idempotency-Key' => self::KEY])->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
        $this->assertSame(1, $item->refresh()->lock_version);
        $this->assertDatabaseCount('help_request_revisions', 0);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidChanges(): iterable
    {
        yield 'empty' => [[], 'changes'];
        yield 'version missing' => [['lock_version' => null, 'title' => 'Titre correct et assez long'], 'lock_version'];
        yield 'overflow' => [['lock_version' => 2147483647, 'title' => 'Titre correct et assez long'], 'lock_version'];
        yield 'null title' => [['title' => null], 'title'];
        yield 'short public title' => [['title' => 'Court'], 'title'];
        yield 'language required' => [['code' => '<script>inerte</script>'], 'code_language'];
        yield 'note short' => [['title' => 'Titre correct et assez long', 'edit_note' => 'court'], 'edit_note'];
        yield 'secret' => [['title' => 'api_key='.str_repeat('a', 40)], 'title'];
        yield 'dangerous link' => [['reproduction_url' => 'javascript:alert(1)'], 'reproduction_url'];
        yield 'unknown intent' => [['help_intent' => 'anything'], 'help_intent'];
    }

    public function test_clarification_of_a_resolved_request_preserves_the_accepted_resolution(): void
    {
        $item = $this->requestFixture('resolved');
        $proposal = Proposal::factory()->create(['request_id' => $item->id, 'state' => 'accepted']);
        $resolution = Resolution::factory()->create(['request_id' => $item->id, 'proposal_id' => $proposal->id]);
        $before = $resolution->refresh()->getAttributes();
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 1, 'title' => 'Précision après la résolution', 'edit_note' => 'Je précise uniquement le contexte de la résolution.'], ['Idempotency-Key' => self::KEY])
            ->assertOk()->assertJsonPath('data.state', 'resolved');
        $this->assertSame($before, $resolution->refresh()->getAttributes());
        $this->assertSame('accepted', $proposal->refresh()->getRawOriginal('state'));
    }

    public function test_existing_language_can_be_reused_for_inert_code_without_fetching_links(): void
    {
        $item = $this->requestFixture('open');
        $item->forceFill(['code_language' => 'html'])->save();
        Http::preventStrayRequests();
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 1, 'code' => '  <script>texte</script>  ', 'reproduction_url' => 'https://example.test/no-fetch'], ['Idempotency-Key' => self::KEY])
            ->assertOk()->assertJsonPath('data.code', '  <script>texte</script>  ')->assertJsonPath('data.code_language', 'html');
        Http::assertNothingSent();
    }

    public function test_session_csrf_key_verification_and_archived_state_are_enforced(): void
    {
        $item = $this->requestFixture();
        foreach (['PATCH' => '', 'POST' => '/publish'] as $method => $suffix) {
            $body = $method === 'PATCH' ? ['lock_version' => 1, 'title' => 'Brouillon'] : ['lock_version' => 1];
            $this->browserRequest($method, '/api/v1/requests/'.$item->id.$suffix, $body, ['Idempotency-Key' => self::KEY], sendXsrf: false)->assertStatus(419);
            $this->browserRequest($method, '/api/v1/requests/'.$item->id.$suffix, $body)->assertUnprocessable()->assertJsonValidationErrors('Idempotency-Key', 'error.fields');
        }
        $item->forceFill(['state' => 'archived'])->save();
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 1, 'title' => 'Brouillon'], ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 1, 'title' => 'Brouillon'], ['Idempotency-Key' => self::KEY])->assertUnauthorized();
        $this->login(User::factory()->create());
        $this->browserRequest('PATCH', '/api/v1/requests/'.$item->id, ['lock_version' => 1, 'title' => 'Brouillon'], ['Idempotency-Key' => self::KEY])->assertForbidden();
    }

    private function requestFixture(string $state = 'draft'): HelpRequest
    {
        $owner = User::factory()->verified()->create();
        $item = HelpRequest::factory()->for($owner, 'author')->create(['state' => $state]);
        $item->technologies()->attach(Technology::factory()->create());
        $this->login($owner);

        return $item;
    }

    private function login(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}
