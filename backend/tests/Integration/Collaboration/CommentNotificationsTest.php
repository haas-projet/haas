<?php

namespace Tests\Integration\Collaboration;

use App\Data\Collaboration\CommentData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Notifications\NotificationEvent;
use App\Models\Comment;
use App\Models\HelpRequest;
use App\Models\Technology;
use App\Models\User;
use App\Services\Collaboration\CommentService;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class CommentNotificationsTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    private const string KEY = 'b1700000-1111-4444-8888-123456789abc';

    private const string BODY = 'Commentaire fictif à ne pas recopier dans une notification.';

    /** @var list<string> */
    private array $parentIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    protected function tearDown(): void
    {
        try {
            // B17 refuse de retirer sa migration tant que ses événements subsistent.
            // Les UUID proviennent uniquement des demandes créées par ce test.
            if ($this->parentIds !== []) {
                $ids = Comment::whereIn('request_id', $this->parentIds)->pluck('id')->all();
                foreach (['internal_notifications', 'notification_outbox'] as $table) {
                    DB::table($table)->where('kind', 'comment.created')->whereIn('event_id', $ids)->delete();
                }
                Comment::whereIn('id', $ids)->delete();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_business_event_is_atomic_and_is_delivered_once_only_after_commit(): void
    {
        $parent = $this->parentFixture();
        $actor = User::factory()->verified()->create();
        $service = app(CommentService::class);
        $outbox = app(NotificationOutbox::class);
        $data = new CommentData(self::BODY);
        $key = new IdempotencyKey(self::KEY);
        config(['database.connections.b17_notification_observer' => config('database.connections.pgsql')]);

        DB::beginTransaction();
        try {
            $rolledBack = $service->create($actor, $parent->id, $data, $key);
            $this->assertDatabaseHas('notification_outbox', ['event_id' => $rolledBack->id, 'recipient_id' => $parent->author_id, 'kind' => 'comment.created', 'delivered_at' => null]);
            $observer = DB::connection('b17_notification_observer');
            $this->assertSame(0, $observer->table('comments')->where('id', $rolledBack->id)->count());
            $this->assertSame(0, $observer->table('notification_outbox')->where('event_id', $rolledBack->id)->count());
            try {
                $outbox->deliver();
                $this->fail('La livraison doit refuser la transaction métier ouverte.');
            } catch (LogicException) {
                $this->assertDatabaseCount('internal_notifications', 0);
            }
        } finally {
            DB::rollBack();
            DB::disconnect('b17_notification_observer');
        }
        $this->assertDatabaseCount('comments', 0);
        $this->assertDatabaseCount('comment_revisions', 0);
        $this->assertDatabaseCount('content_revisions', 0);
        $this->assertDatabaseCount('api_idempotency', 0);
        $this->assertDatabaseCount('notification_outbox', 0);

        $comment = $service->create($actor, $parent->id, $data, $key);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'body' => self::BODY]);
        $this->assertDatabaseHas('notification_outbox', ['event_id' => $comment->id, 'recipient_id' => $parent->author_id, 'kind' => 'comment.created', 'delivered_at' => null]);
        $this->assertDatabaseCount('internal_notifications', 0);
        $this->assertSame(1, $outbox->deliver());
        $this->assertSame(0, DB::transactionLevel());
        $this->assertNotNull(DB::table('notification_outbox')->where('event_id', $comment->id)->value('delivered_at'));
        $this->assertDatabaseHas('internal_notifications', ['event_id' => $comment->id, 'recipient_id' => $parent->author_id, 'kind' => 'comment.created']);

        DB::transaction(fn () => $outbox->record(new NotificationEvent($comment->id, $parent->author_id, 'comment.created')));
        $this->assertSame(0, $outbox->deliver());
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('internal_notifications', 1);
        $this->login(User::findOrFail($parent->author_id));
        $inbox = $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.kind', 'comment.created')->assertJsonPath('data.0.message', 'Un commentaire a été ajouté à votre demande.')
            ->assertJsonPath('data.0.target_path', '/requests/'.$parent->id);
        $this->assertEqualsCanonicalizing(['id', 'kind', 'message', 'target_path', 'read_at', 'created_at'], array_keys($inbox->json('data.0')));
        $content = $inbox->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString(self::BODY, $content);
        $this->assertStringNotContainsString($actor->email, $content);
    }

    public function test_self_comments_and_edits_do_not_schedule_new_notifications(): void
    {
        $parent = $this->parentFixture();
        $owner = User::findOrFail($parent->author_id);
        $selfComment = $this->createComment($owner, $parent);
        $this->browserRequest('PATCH', '/api/v1/comments/'.$selfComment, ['body' => 'Précision de l’auteur du fil.', 'lock_version' => 1], ['Idempotency-Key' => self::KEY])->assertOk();
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());

        $actor = User::factory()->verified()->create();
        $comment = $this->createComment($actor, $parent);
        $edit = ['body' => 'Précision du commentaire tiers.', 'lock_version' => 1];
        $first = $this->browserRequest('PATCH', '/api/v1/comments/'.$comment, $edit, ['Idempotency-Key' => self::KEY])->assertOk();
        $this->browserRequest('PATCH', '/api/v1/comments/'.$comment, $edit, ['Idempotency-Key' => self::KEY])->assertOk()->assertExactJson($first->json());
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseMissing('notification_outbox', ['event_id' => $selfComment]);
        $this->assertDatabaseHas('notification_outbox', ['event_id' => $comment, 'recipient_id' => $owner->id, 'kind' => 'comment.created']);
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('unread_count', 0);
        $this->login($owner);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1);
    }

    public function test_hidden_comment_disappears_from_inbox_count_mark_and_current_replay(): void
    {
        $parent = $this->parentFixture();
        $actor = User::factory()->verified()->create();
        $id = $this->createComment($actor, $parent);
        $created = $this->browserRequest('GET', '/api/v1/comments/'.$id)->assertOk()->json();
        $path = '/api/v1/requests/'.$parent->id.'/comments';
        $this->browserRequest('POST', $path, ['body' => self::BODY], ['Idempotency-Key' => self::KEY])->assertCreated()->assertExactJson($created);
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $notificationId = (string) DB::table('internal_notifications')->where('event_id', $id)->value('id');
        $this->login(User::findOrFail($parent->author_id));
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1);

        Comment::whereKey($id)->update(['hidden_at' => now()]);
        $this->assertUnavailableInInbox($notificationId);
        $this->login($actor);
        $this->browserRequest('POST', $path, ['body' => self::BODY], ['Idempotency-Key' => self::KEY])->assertNotFound();
        Comment::whereKey($id)->update(['hidden_at' => null]);
        $this->browserRequest('POST', $path, ['body' => self::BODY], ['Idempotency-Key' => self::KEY])->assertCreated()->assertExactJson($created);
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('internal_notifications', 1);
        $this->login(User::findOrFail($parent->author_id));
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1);
        $readAt = $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertOk()->json('data.read_at');
        $this->assertNotNull($readAt);
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertOk()->assertJsonPath('data.read_at', $readAt);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('unread_count', 0);
    }

    public function test_hidden_parent_disappears_from_inbox_count_mark_and_current_replay(): void
    {
        $parent = $this->parentFixture();
        $actor = User::factory()->verified()->create();
        $id = $this->createComment($actor, $parent);
        $created = $this->browserRequest('GET', '/api/v1/comments/'.$id)->assertOk()->json();
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $notificationId = (string) DB::table('internal_notifications')->where('event_id', $id)->value('id');
        $this->login(User::findOrFail($parent->author_id));
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1);

        $parent->forceFill(['hidden_at' => now()])->save();
        $this->assertUnavailableInInbox($notificationId);
        $this->login($actor);
        $path = '/api/v1/requests/'.$parent->id.'/comments';
        $this->browserRequest('POST', $path, ['body' => self::BODY], ['Idempotency-Key' => self::KEY])->assertNotFound();
        $parent->forceFill(['hidden_at' => null])->save();
        $this->browserRequest('POST', $path, ['body' => self::BODY], ['Idempotency-Key' => self::KEY])->assertCreated()->assertExactJson($created);
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('internal_notifications', 1);
        $this->login(User::findOrFail($parent->author_id));
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1);
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertOk();
    }

    #[DataProvider('currentVisibilityChanges')]
    public function test_draft_parent_or_unavailable_comment_author_excludes_existing_notification(string $kind): void
    {
        $parent = $this->parentFixture();
        $actor = User::factory()->verified()->create();
        $id = $this->createComment($actor, $parent);
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $notificationId = (string) DB::table('internal_notifications')->where('event_id', $id)->value('id');
        $owner = User::findOrFail($parent->author_id);
        $this->login($owner);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1);

        match ($kind) {
            'draft' => $parent->forceFill(['state' => 'draft'])->save(),
            'suspended_author' => $actor->forceFill(['status' => 'suspended'])->save(),
            'unverified_author' => $actor->forceFill(['email_verified_at' => null])->save(),
            default => throw new \InvalidArgumentException('Fixture de visibilité des notifications B17 inconnue.'),
        };

        $this->assertSame('active', $owner->refresh()->status->value);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertUnavailableInInbox($notificationId);
        $this->assertDatabaseHas('internal_notifications', ['id' => $notificationId, 'recipient_id' => $owner->id, 'event_id' => $id, 'kind' => 'comment.created']);
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
    }

    /** @return iterable<string, array{string}> */
    public static function currentVisibilityChanges(): iterable
    {
        foreach (['draft', 'suspended_author', 'unverified_author'] as $kind) {
            yield $kind => [$kind];
        }
    }

    private function assertUnavailableInInbox(string $notificationId): void
    {
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('unread_count', 0)->assertJsonCount(0, 'data');
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertNotFound();
        $this->assertDatabaseHas('internal_notifications', ['id' => $notificationId, 'read_at' => null]);
    }

    private function createComment(User $actor, HelpRequest $parent): string
    {
        $this->login($actor);

        return (string) $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/comments', ['body' => self::BODY], ['Idempotency-Key' => self::KEY])->assertCreated()->json('data.id');
    }

    private function parentFixture(): HelpRequest
    {
        $parent = HelpRequest::factory()->for(User::factory()->verified(), 'author')->create(['state' => 'open']);
        $this->parentIds[] = $parent->id;
        $parent->technologies()->attach(Technology::factory()->create());

        return $parent;
    }

    private function login(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}
