<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Data\Capsules\ReviewCommandData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Notifications\NotificationEvent;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Identity\Role;
use App\Exceptions\NotificationStorageFailed;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\RequestChangesOnCapsuleVersionService;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\PostgresTestCase;
use Tests\Support\CapsuleReviewNotificationFixtures;
use Tests\Support\SpaHttpRequests;

final class CapsuleReviewNotificationsTest extends PostgresTestCase
{
    use CapsuleReviewNotificationFixtures, DatabaseMigrations, SpaHttpRequests;

    private const string NOTE = 'Note fictive détaillée : complétez les étapes avant resoumission.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_atomic_intention_is_delivered_after_commit_once_without_copying_the_note(): void
    {
        [$owner, $reviewer, $capsule, $version] = $this->fixture();
        $service = app(RequestChangesOnCapsuleVersionService::class);
        $outbox = app(NotificationOutbox::class);
        $command = new ReviewCommandData(1, self::NOTE);
        $key = new IdempotencyKey((string) Str::uuid());
        config(['database.connections.b24_notification_observer' => config('database.connections.pgsql')]);
        DB::beginTransaction();
        try {
            $review = $service->handle($reviewer, $capsule, $version, $command, $key);
            $this->assertDatabaseHas('notification_outbox', ['event_id' => $review->id, 'recipient_id' => $owner->id, 'kind' => 'capsule.review.changes_requested', 'delivered_at' => null]);
            $observer = DB::connection('b24_notification_observer');
            $this->assertSame(0, $observer->table('capsule_version_reviews')->where('id', $review->id)->count());
            $this->assertSame(0, $observer->table('notification_outbox')->where('event_id', $review->id)->count());
            try {
                $outbox->deliver();
                $this->fail('La livraison ne doit pas voir une transaction métier ouverte.');
            } catch (LogicException) {
                $this->assertDatabaseCount('internal_notifications', 0);
            }
        } finally {
            DB::rollBack();
            DB::disconnect('b24_notification_observer');
        }
        foreach (['capsule_version_reviews', 'content_revisions', 'api_idempotency', 'notification_outbox'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(CapsuleVersionState::InReview, $version->refresh()->state);
        $this->assertSame(1, $version->lock_version);
        $review = $service->handle($reviewer, $capsule, $version, $command, $key);
        $this->assertSame($review->id, $service->handle($reviewer, $capsule, $version, $command, $key)->id);
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('internal_notifications', 0);
        $this->assertSame(1, $outbox->deliver());
        DB::transaction(fn () => $outbox->record(new NotificationEvent($review->id, $owner->id, 'capsule.review.changes_requested')));
        $this->assertSame(0, $outbox->deliver());
        $this->assertDatabaseCount('internal_notifications', 1);
        $this->login($owner);
        $inbox = $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.kind', 'capsule.review.changes_requested')
            ->assertJsonPath('data.0.message', 'Des corrections ont été demandées sur votre brouillon de capsule.')
            ->assertJsonPath('data.0.target_path', '/capsules/'.$capsule->id.'/versions/'.$version->id.'/edition');
        $this->assertEqualsCanonicalizing(['id', 'kind', 'message', 'target_path', 'read_at', 'created_at'], array_keys($inbox->json('data.0')));
        $content = $inbox->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString(self::NOTE, $content);
        $this->assertStringNotContainsString($version->body, $content);
        $this->assertStringNotContainsString($reviewer->email, $content);
        $notificationId = $inbox->json('data.0.id');
        $readAt = $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertOk()->json('data.read_at');
        $this->assertNotNull($readAt);
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertOk()->assertJsonPath('data.read_at', $readAt);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('unread_count', 0);
        $this->login(User::factory()->verified()->create(['role' => Role::Admin]));
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 0);
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertNotFound();
    }

    public function test_hidden_capsule_is_excluded_before_pagination_count_and_mark(): void
    {
        [$owner, $reviewer, $hiddenCapsule, $hiddenVersion] = $this->fixture();
        $hidden = app(RequestChangesOnCapsuleVersionService::class)->handle($reviewer, $hiddenCapsule, $hiddenVersion, new ReviewCommandData(1, self::NOTE), new IdempotencyKey((string) Str::uuid()));
        [, $otherReviewer, $visibleCapsule, $visibleVersion] = $this->fixture($owner);
        app(RequestChangesOnCapsuleVersionService::class)->handle($otherReviewer, $visibleCapsule, $visibleVersion, new ReviewCommandData(1, self::NOTE), new IdempotencyKey((string) Str::uuid()));
        $this->assertSame(2, app(NotificationOutbox::class)->deliver());
        $hiddenId = DB::table('internal_notifications')->where('event_id', $hidden->id)->value('id');
        $hiddenCapsule->forceFill(['visibility' => CapsuleVisibility::Hidden])->save();
        $this->login($owner);
        $this->browserRequest('GET', '/api/v1/notifications?per_page=1')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target_path', '/capsules/'.$visibleCapsule->id.'/versions/'.$visibleVersion->id.'/edition');
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$hiddenId, ['read' => true])->assertNotFound();
        $this->assertDatabaseHas('internal_notifications', ['id' => $hiddenId, 'read_at' => null]);
        $hiddenCapsule->forceFill(['visibility' => CapsuleVisibility::Visible])->save();
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('unread_count', 2);
        $this->assertDatabaseCount('capsule_version_reviews', 2);
    }

    public function test_transfer_and_unverification_remove_current_access_without_erasing_attribution(): void
    {
        [$owner, $reviewer, $capsule, $version] = $this->fixture();
        $review = app(RequestChangesOnCapsuleVersionService::class)->handle($reviewer, $capsule, $version, new ReviewCommandData(1, self::NOTE), new IdempotencyKey((string) Str::uuid()));
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $notificationId = DB::table('internal_notifications')->where('event_id', $review->id)->value('id');
        if (! is_string($notificationId)) {
            throw new RuntimeException('Notification fictive attendue introuvable.');
        }
        $this->login($owner);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1);
        $owner->forceFill(['email_verified_at' => null])->save();
        $this->assertUnavailable($notificationId);
        $owner->forceFill(['email_verified_at' => now()])->save();
        $capsule->forceFill(['owner_id' => User::factory()->verified()->create()->id])->save();
        $this->assertUnavailable($notificationId);
        $this->assertDatabaseHas('capsule_version_reviews', ['id' => $review->id, 'reviewer_id' => $reviewer->id, 'reviewed_lock_version' => 1, 'note' => self::NOTE]);
        $this->assertDatabaseHas('internal_notifications', ['id' => $notificationId, 'recipient_id' => $owner->id, 'read_at' => null]);
    }

    public function test_record_failure_rolls_back_review_audit_and_idempotency(): void
    {
        [$owner, $reviewer, $capsule, $version] = $this->fixture();
        DB::unprepared("CREATE FUNCTION reject_b24_outbox_fixture() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''échec fictif intention''; END'; CREATE TRIGGER reject_b24_outbox_fixture BEFORE INSERT ON notification_outbox FOR EACH ROW EXECUTE FUNCTION reject_b24_outbox_fixture()");
        try {
            app(RequestChangesOnCapsuleVersionService::class)->handle($reviewer, $capsule, $version, new ReviewCommandData(1, self::NOTE), new IdempotencyKey((string) Str::uuid()));
            $this->fail('La panne SQL d’intention doit remonter.');
        } catch (NotificationStorageFailed) {
            $this->assertSame(CapsuleVersionState::InReview, $version->refresh()->state);
            $this->assertSame(1, $version->lock_version);
            $this->assertNull($version->reviewer_id);
            foreach (['capsule_version_reviews', 'content_revisions', 'api_idempotency', 'notification_outbox'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
        } finally {
            DB::unprepared('DROP TRIGGER reject_b24_outbox_fixture ON notification_outbox; DROP FUNCTION reject_b24_outbox_fixture()');
        }
    }

    public function test_delivery_failure_keeps_committed_review_and_retryable_intention(): void
    {
        [, $reviewer, $capsule, $version] = $this->fixture();
        $review = app(RequestChangesOnCapsuleVersionService::class)->handle($reviewer, $capsule, $version, new ReviewCommandData(1, self::NOTE), new IdempotencyKey((string) Str::uuid()));
        DB::unprepared("CREATE FUNCTION reject_b24_delivery_fixture() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''échec fictif livraison''; END'; CREATE TRIGGER reject_b24_delivery_fixture BEFORE INSERT ON internal_notifications FOR EACH ROW EXECUTE FUNCTION reject_b24_delivery_fixture()");
        try {
            app(NotificationOutbox::class)->deliver();
            $this->fail('La panne de livraison doit remonter.');
        } catch (NotificationStorageFailed) {
            $this->assertSame(CapsuleVersionState::ChangesRequested, $version->refresh()->state);
            $this->assertDatabaseHas('capsule_version_reviews', ['id' => $review->id]);
            $this->assertDatabaseHas('notification_outbox', ['event_id' => $review->id, 'delivered_at' => null]);
            $this->assertDatabaseCount('internal_notifications', 0);
        } finally {
            DB::unprepared('DROP TRIGGER reject_b24_delivery_fixture ON internal_notifications; DROP FUNCTION reject_b24_delivery_fixture()');
        }
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
        $this->assertDatabaseCount('internal_notifications', 1);
    }

    public function test_notification_migration_refuses_to_erase_stable_review_events(): void
    {
        [, $reviewer, $capsule, $version] = $this->fixture();
        $review = app(RequestChangesOnCapsuleVersionService::class)->handle($reviewer, $capsule, $version, new ReviewCommandData(1, self::NOTE), new IdempotencyKey((string) Str::uuid()));
        $migration = require base_path('database/migrations/2026_10_07_200000_b24_capsule_review_notification_events.php');
        $failure = null;
        try {
            $migration->down();
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }
        $this->assertInstanceOf(RuntimeException::class, $failure, 'Le rollback ne doit pas effacer les événements stables.');
        $this->assertStringContainsString('événements de revue à conserver', $failure->getMessage());
        $this->assertDatabaseHas('notification_outbox', ['event_id' => $review->id]);
        $this->assertDatabaseHas('capsule_version_reviews', ['id' => $review->id]);
    }

    private function assertUnavailable(string $notificationId): void
    {
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('unread_count', 0)->assertJsonCount(0, 'data');
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertNotFound();
    }

    /** @return array{User, User, Capsule, CapsuleVersion} */
    private function fixture(?User $owner = null): array
    {
        $owner ??= User::factory()->verified()->create();
        $reviewer = User::factory()->verified()->create(['role' => Role::Moderator]);
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id]);
        $version = CapsuleVersion::factory()->create(['capsule_id' => $capsule->id, 'state' => CapsuleVersionState::InReview, 'limits' => 'Limites explicitement documentées.']);

        return [$owner, $reviewer, $capsule, $version];
    }

    private function login(User $actor): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $actor->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}
