<?php

namespace Tests\Integration\Collaboration;

use App\Enums\Identity\Role;
use App\Models\Comment;
use App\Models\CommentRevision;
use App\Models\HelpRequest;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class CommentsTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    private const string KEY = 'b1700000-1111-4444-8888-123456789abc';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_create_replay_and_read_preserve_parent_and_generate_one_private_notification_intention(): void
    {
        $parent = $this->parentFixture();
        $actor = User::factory()->verified()->create();
        $this->login($actor);
        $original = $parent->refresh()->getAttributes();
        $body = ['body' => "  **Explication**\n\n```html\n<script>texte</script>\n```\n\n![Image](https://example.test/pixel)  "];
        Http::preventStrayRequests();
        $first = $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', $body, ['Idempotency-Key' => self::KEY])->assertCreated()->assertJsonPath('data.body', $body['body'])->assertJsonPath('data.lock_version', 1)->assertJsonPath('data.author.id', $actor->id)->assertJsonPath('data.edited_at', null);
        $this->browserRequest('POST', '/api/v1/requests/'.strtoupper($parent->id).'/comments', $body, ['Idempotency-Key' => strtoupper(self::KEY)])->assertCreated()->assertExactJson($first->json());
        $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', ['body' => 'Autre intention'], ['Idempotency-Key' => self::KEY])->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame($original, $parent->refresh()->getAttributes());
        $comment = Comment::sole();
        $this->assertDatabaseCount('comment_revisions', 1);
        $this->assertDatabaseCount('content_revisions', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->assertDatabaseCount('internal_notifications', 0);
        $this->assertDatabaseHas('notification_outbox', ['recipient_id' => $parent->author_id, 'event_id' => $comment->id, 'kind' => 'comment.created', 'delivered_at' => null]);
        $metadata = json_decode(DB::table('content_revisions')->sole()->metadata, true);
        $this->assertEquals(['request_id' => $parent->id, 'comment_version' => 1], $metadata);
        $this->assertStringNotContainsString('<script>', $first->json('data.body_html'));
        $this->assertStringNotContainsString('<img', $first->json('data.body_html'));
        Http::assertNothingSent();
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->browserRequest('GET', '/api/v1/comments/'.$comment->id)->assertOk()->assertExactJson($first->json())->assertHeader('Cache-Control', 'no-store, private');
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/comments')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonCount(1, 'data');
    }

    public function test_edit_versions_history_and_replays_are_consistent(): void
    {
        $parent = $this->parentFixture('resolved');
        $actor = User::factory()->verified()->create();
        $this->login($actor);
        $created = $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', ['body' => 'Message initial'], ['Idempotency-Key' => self::KEY])->assertCreated();
        $id = $created->json('data.id');
        $data = ['body' => 'Message clarifié', 'lock_version' => 1];
        $first = $this->browserRequest('PATCH', '/api/v1/comments/'.$id, $data, ['Idempotency-Key' => self::KEY])->assertOk()->assertJsonPath('data.lock_version', 2);
        $this->assertNotNull($first->json('data.edited_at'));
        $this->browserRequest('PATCH', '/api/v1/comments/'.$id, $data, ['Idempotency-Key' => self::KEY])->assertOk()->assertExactJson($first->json());
        $this->browserRequest('PATCH', '/api/v1/comments/'.$id, $data, ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
        $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', ['body' => 'Message initial'], ['Idempotency-Key' => self::KEY])->assertStatus(409);
        $this->browserRequest('GET', '/api/v1/comments/'.$id.'/revisions?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.body', 'Message clarifié')->assertJsonPath('data.0.comment_version', 2);
        $this->browserRequest('GET', '/api/v1/comments/'.$id.'/revisions?page=2&per_page=1')->assertOk()->assertJsonPath('data.0.body', 'Message initial');
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('content_revisions', 2);
        $this->assertDatabaseCount('api_idempotency', 2);
        $this->assertSame('resolved', $parent->refresh()->state->value);
        $this->assertSame(1, $parent->lock_version);
    }

    public function test_first_edit_of_legacy_comment_captures_only_its_known_version(): void
    {
        $parent = $this->parentFixture();
        $actor = User::factory()->verified()->create();
        $this->login($actor);
        $comment = Comment::factory()->create(['request_id' => $parent->id, 'author_id' => $actor->id, 'lock_version' => 7, 'body' => 'Version historique connue']);
        $this->browserRequest('PATCH', '/api/v1/comments/'.$comment->id, ['body' => 'Précision actuelle', 'lock_version' => 7], ['Idempotency-Key' => self::KEY])->assertOk();
        $this->assertSame([7, 8], CommentRevision::orderBy('comment_version')->pluck('comment_version')->all());
        $this->assertDatabaseHas('comment_revisions', ['comment_version' => 7, 'action' => 'before_edit', 'body' => 'Version historique connue']);
    }

    #[DataProvider('roles')]
    public function test_third_party_cannot_edit_even_with_privileged_role(Role $role): void
    {
        $parent = $this->parentFixture();
        $comment = Comment::factory()->create(['request_id' => $parent->id, 'author_id' => User::factory()->verified()]);
        $this->login(User::factory()->verified()->create(['role' => $role]));
        $this->browserRequest('PATCH', '/api/v1/comments/'.$comment->id, ['body' => 'Usurpation', 'lock_version' => 1], ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertSame(1, $comment->refresh()->lock_version);
        $this->assertDatabaseCount('comment_revisions', 0);
    }

    /** @return iterable<string, array{Role}> */
    public static function roles(): iterable
    {
        foreach (Role::cases() as $role) {
            yield $role->value => [$role];
        }
    }

    #[DataProvider('invisibleKinds')]
    public function test_hidden_parent_or_comment_or_suspended_author_disappears_from_all_reads_and_replay(string $kind): void
    {
        $parent = $this->parentFixture();
        $actor = User::factory()->verified()->create();
        $this->login($actor);
        $created = $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', ['body' => 'Texte devenu inaccessible'], ['Idempotency-Key' => self::KEY])->assertCreated();
        $id = $created->json('data.id');
        match ($kind) {
            'parent' => $parent->forceFill(['hidden_at' => now()])->save(),
            'owner' => User::whereKey($parent->author_id)->update(['status' => 'suspended']),
            'author' => $actor->forceFill(['status' => 'suspended'])->save(),
            'comment' => Comment::whereKey($id)->update(['hidden_at' => now()]),
            'draft' => $parent->forceFill(['state' => 'draft'])->save(),
            default => throw new \InvalidArgumentException('Fixture de visibilité B17 inconnue.'),
        };
        $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', ['body' => 'Texte devenu inaccessible'], ['Idempotency-Key' => self::KEY])->assertStatus($kind === 'author' ? 403 : 404);
        // Lecture publique sans réutiliser la session suspendue.
        $this->browserCookies = [];
        $this->browserRequest('GET', '/api/v1/comments/'.$id)->assertNotFound();
        $this->browserRequest('GET', '/api/v1/comments/'.$id.'/revisions')->assertNotFound();
        $list = $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/comments');
        if (in_array($kind, ['parent', 'owner', 'draft'], true)) {
            $list->assertNotFound();
        } else {
            $list->assertOk()->assertJsonPath('meta.total', 0);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invisibleKinds(): iterable
    {
        foreach (['parent', 'owner', 'author', 'comment', 'draft'] as $kind) {
            yield $kind => [$kind];
        }
    }

    public function test_archives_and_drafts_refuse_writes_but_archives_remain_readable(): void
    {
        $parent = $this->parentFixture('draft');
        $owner = User::findOrFail($parent->author_id);
        $this->login($owner);
        $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', ['body' => 'Message'], ['Idempotency-Key' => self::KEY])->assertForbidden();
        $parent->forceFill(['state' => 'archived'])->save();
        $comment = Comment::factory()->create(['request_id' => $parent->id, 'author_id' => $owner->id]);
        $this->browserRequest('PATCH', '/api/v1/comments/'.$comment->id, ['body' => 'Message', 'lock_version' => 1], ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', ['body' => 'Message'], ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->browserRequest('GET', '/api/v1/comments/'.$comment->id)->assertOk();
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidBodies')]
    public function test_invalid_or_protected_input_is_rejected_without_side_effect(array $payload, string $field): void
    {
        $parent = $this->parentFixture();
        $this->login(User::factory()->verified()->create());
        $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', $payload, ['Idempotency-Key' => self::KEY])->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
        foreach (['comments', 'comment_revisions', 'content_revisions', 'notification_outbox', 'api_idempotency'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidBodies(): iterable
    {
        yield 'missing' => [[], 'body'];
        yield 'null' => [['body' => null], 'body'];
        yield 'spaces' => [['body' => " \t\n "], 'body'];
        yield 'long' => [['body' => str_repeat('é', 4001)], 'body'];
        yield 'secret' => [['body' => 'api_key='.str_repeat('a', 40)], 'body'];
        yield 'control' => [['body' => "texte\x01"], 'body'];
        yield 'array' => [['body' => ['x']], 'body'];
        foreach (['author_id', 'request_id', 'state', 'hidden_at', 'created_at', 'edited_at', 'lock_version', 'body_html'] as $field) {
            yield $field => [['body' => 'Texte', $field => null], $field];
        }
    }

    public function test_edit_requires_version_and_rejects_injected_fields(): void
    {
        $parent = $this->parentFixture();
        $actor = User::factory()->verified()->create();
        $this->login($actor);
        $comment = Comment::factory()->create(['request_id' => $parent->id, 'author_id' => $actor->id]);
        foreach ([null, 0, 2147483647, 'bad'] as $version) {
            $this->browserRequest('PATCH', '/api/v1/comments/'.$comment->id, ['body' => 'Texte', 'lock_version' => $version], ['Idempotency-Key' => self::KEY])->assertUnprocessable();
        }
        $this->browserRequest('PATCH', '/api/v1/comments/'.$comment->id, ['body' => 'Texte', 'lock_version' => 1, 'author_id' => null], ['Idempotency-Key' => self::KEY])->assertUnprocessable();
        $this->assertSame(1, $comment->refresh()->lock_version);
    }

    #[DataProvider('failureTables')]
    public function test_storage_failure_rolls_back_the_entire_create_and_can_be_retried(string $table): void
    {
        $parent = $this->parentFixture();
        $this->login(User::factory()->verified()->create());
        DB::statement('ALTER TABLE '.$table.' ADD CONSTRAINT fixture_b17_failure CHECK (false)');
        $response = $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', ['body' => 'Texte à ne pas recopier dans une erreur'], ['Idempotency-Key' => self::KEY])->assertStatus(500);
        $this->assertStringNotContainsString('Texte à ne pas recopier', (string) $response->getContent());
        foreach (['comments', 'comment_revisions', 'content_revisions', 'notification_outbox', 'api_idempotency'] as $storage) {
            $this->assertDatabaseCount($storage, 0);
        }
        DB::statement('ALTER TABLE '.$table.' DROP CONSTRAINT fixture_b17_failure');
        $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', ['body' => 'Texte à ne pas recopier dans une erreur'], ['Idempotency-Key' => self::KEY])->assertCreated();
    }

    /** @return iterable<string, array{string}> */
    public static function failureTables(): iterable
    {
        foreach (['comment_revisions', 'content_revisions', 'notification_outbox', 'api_idempotency'] as $table) {
            yield $table => [$table];
        }
    }

    public function test_session_csrf_key_and_verification_are_required(): void
    {
        $parent = $this->parentFixture();
        $path = '/api/v1/requests/'.$parent->id.'/comments';
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', $path, ['body' => 'Texte'], ['Idempotency-Key' => self::KEY])->assertUnauthorized();
        $actor = User::factory()->verified()->create();
        $this->login($actor);
        $this->browserRequest('POST', $path, ['body' => 'Texte'], ['Idempotency-Key' => self::KEY], sendXsrf: false)->assertStatus(419);
        $this->browserRequest('POST', $path, ['body' => 'Texte'])->assertUnprocessable()->assertJsonValidationErrors('Idempotency-Key', 'error.fields');
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->login(User::factory()->create());
        $this->browserRequest('POST', $path, ['body' => 'Texte'], ['Idempotency-Key' => self::KEY])->assertForbidden();
    }

    public function test_comment_makes_b16_note_mandatory_without_changing_request_state(): void
    {
        $parent = $this->parentFixture();
        $owner = User::findOrFail($parent->author_id);
        $this->login($owner);
        $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', ['body' => 'Ma précision'], ['Idempotency-Key' => self::KEY])->assertCreated();
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->browserRequest('PATCH', '/api/v1/requests/'.$parent->id, ['title' => 'Titre clarifié avec contribution', 'lock_version' => 1], ['Idempotency-Key' => self::KEY])->assertUnprocessable()->assertJsonValidationErrors('edit_note', 'error.fields');
        $this->assertSame('open', $parent->refresh()->state->value);
    }

    private function parentFixture(string $state = 'open'): HelpRequest
    {
        $parent = HelpRequest::factory()->for(User::factory()->verified(), 'author')->create(['state' => $state]);
        $parent->technologies()->attach(Technology::factory()->create());

        return $parent;
    }

    private function login(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}
