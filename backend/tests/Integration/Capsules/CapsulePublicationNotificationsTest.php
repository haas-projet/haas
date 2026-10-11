<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Data\Capsules\PublishCommandData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Notifications\NotificationEvent;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Capsules\ContributionRole;
use App\Enums\Capsules\ReviewDecision;
use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Exceptions\Capsules\StaleCapsuleVersion;
use App\Exceptions\NotificationStorageFailed;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleContributor;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Capsules\CapsuleVersionReview;
use App\Models\User;
use App\Services\Capsules\PublishCapsuleVersionService;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\PostgresTestCase;
use Tests\Support\CapsulePublicationFixtures;
use Tests\Support\SpaHttpRequests;

final class CapsulePublicationNotificationsTest extends PostgresTestCase
{
    use CapsulePublicationFixtures, DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_atomic_publication_intention_delivers_once_after_commit_with_a_stable_version_target(): void
    {
        [$owner, $publisher, $capsule, $version] = $this->fixture();
        $service = app(PublishCapsuleVersionService::class);
        $outbox = app(NotificationOutbox::class);
        $key = new IdempotencyKey((string) Str::uuid());
        config(['database.connections.b25_observer' => config('database.connections.pgsql')]);
        DB::beginTransaction();
        try {
            $service->handle($publisher, $capsule, $version, new PublishCommandData(1), $key);
            $review = DB::table('capsule_version_reviews')->sole();
            $this->assertDatabaseHas('notification_outbox', ['event_id' => $review->id, 'recipient_id' => $owner->id, 'kind' => 'capsule.version.published', 'delivered_at' => null]);
            $observer = DB::connection('b25_observer');
            $this->assertSame(0, $observer->table('capsule_version_reviews')->count());
            $this->assertSame(0, $observer->table('notification_outbox')->count());
            $refused = false;
            try {
                $outbox->deliver();
            } catch (LogicException) {
                $refused = true;
            }
            $this->assertTrue($refused);
            $this->assertDatabaseCount('internal_notifications', 0);
        } finally {
            DB::rollBack();
            DB::disconnect('b25_observer');
        }
        foreach (['capsule_version_reviews', 'content_revisions', 'api_idempotency', 'notification_outbox'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(CapsuleVersionState::InReview, $version->refresh()->state);
        $this->assertNull($version->content_digest);
        $published = $service->handle($publisher, $capsule, $version, new PublishCommandData(1), $key);
        $review = DB::table('capsule_version_reviews')->sole();
        $this->assertSame(1, $review->reviewed_lock_version);
        $this->assertSame($published->id, $service->handle($publisher, $capsule, $version, new PublishCommandData(1), $key)->id);
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('internal_notifications', 0);
        $this->assertSame(1, $outbox->deliver());
        DB::transaction(fn () => $outbox->record(new NotificationEvent($review->id, $owner->id, 'capsule.version.published')));
        $this->assertSame(0, $outbox->deliver());
        $this->login($owner);
        $inbox = $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.kind', 'capsule.version.published')
            ->assertJsonPath('data.0.message', 'Une version de votre capsule a été publiée.')
            ->assertJsonPath('data.0.target_path', '/capsules/'.$capsule->id.'/versions/'.$version->id.'/edition');
        $this->assertEqualsCanonicalizing(['id', 'kind', 'message', 'target_path', 'read_at', 'created_at'], array_keys($inbox->json('data.0')));
        $content = $inbox->getContent();
        $this->assertIsString($content);
        foreach ([$version->body, $version->limits, $publisher->email, $published->content_digest] as $private) {
            $this->assertIsString($private);
            $this->assertStringNotContainsString($private, $content);
        }
        $id = $inbox->json('data.0.id');
        $readAt = $this->browserRequest('PATCH', '/api/v1/notifications/'.$id, ['read' => true])->assertOk()->json('data.read_at');
        $this->assertNotNull($readAt);
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$id, ['read' => true])->assertOk()->assertJsonPath('data.read_at', $readAt);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('unread_count', 0);
        $this->login($publisher);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 0);
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$id, ['read' => true])->assertNotFound();
    }

    public function test_hidden_and_withdrawn_versions_are_filtered_before_pagination_count_and_mark(): void
    {
        [$owner, $publisher, $hiddenCapsule, $hiddenVersion] = $this->fixture();
        $this->publish($publisher, $hiddenCapsule, $hiddenVersion);
        [, $publisher, $withdrawnCapsule, $withdrawn] = $this->fixture($owner);
        $this->publish($publisher, $withdrawnCapsule, $withdrawn);
        [, $publisher, $visibleCapsule, $visible] = $this->fixture($owner);
        $this->publish($publisher, $visibleCapsule, $visible);
        $this->assertSame(3, app(NotificationOutbox::class)->deliver());
        $ids = DB::table('internal_notifications')->pluck('id')->all();
        $hiddenCapsule->forceFill(['visibility' => CapsuleVisibility::Hidden])->save();
        $withdrawn->forceFill(['state' => CapsuleVersionState::Withdrawn, 'lock_version' => 3])->save();
        $this->login($owner);
        $inbox = $this->browserRequest('GET', '/api/v1/notifications?per_page=1')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target_path', '/capsules/'.$visibleCapsule->id.'/versions/'.$visible->id.'/edition');
        foreach ($ids as $id) {
            if ($id !== $inbox->json('data.0.id')) {
                $this->browserRequest('PATCH', '/api/v1/notifications/'.$id, ['read' => true])->assertNotFound();
                $this->assertDatabaseHas('internal_notifications', ['id' => $id, 'read_at' => null]);
            }
        }
        $this->assertDatabaseCount('capsule_version_reviews', 3);
        $this->assertNotNull($withdrawn->refresh()->content_digest);
    }

    public function test_current_owner_verification_and_status_control_inbox_without_erasing_history(): void
    {
        [$owner, $publisher, $capsule, $version] = $this->fixture();
        $this->publish($publisher, $capsule, $version);
        app(NotificationOutbox::class)->deliver();
        $id = DB::table('internal_notifications')->sole()->id;
        $this->login($owner);
        $owner->forceFill(['email_verified_at' => null])->save();
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('unread_count', 0);
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$id, ['read' => true])->assertNotFound();
        $owner->forceFill(['email_verified_at' => now(), 'status' => AccountStatus::Suspended])->save();
        $this->browserRequest('GET', '/api/v1/notifications')->assertForbidden();
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$id, ['read' => true])->assertUnauthorized();
        $owner->forceFill(['status' => AccountStatus::Active])->save();
        $this->login($owner);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1);
        $this->assertDatabaseCount('capsule_version_reviews', 1);
        $this->assertDatabaseHas('internal_notifications', ['id' => $id, 'recipient_id' => $owner->id, 'read_at' => null]);
    }

    public function test_outbox_failure_rolls_back_publication_digest_audit_and_idempotency(): void
    {
        [, $publisher, $capsule, $version] = $this->fixture();
        DB::unprepared("CREATE FUNCTION reject_b25_outbox_fixture() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''échec fictif intention''; END'; CREATE TRIGGER reject_b25_outbox_fixture BEFORE INSERT ON notification_outbox FOR EACH ROW EXECUTE FUNCTION reject_b25_outbox_fixture()");
        $refused = false;
        try {
            $this->publish($publisher, $capsule, $version);
        } catch (NotificationStorageFailed) {
            $refused = true;
        } finally {
            DB::unprepared('DROP TRIGGER reject_b25_outbox_fixture ON notification_outbox; DROP FUNCTION reject_b25_outbox_fixture()');
        }
        $this->assertTrue($refused);
        $this->assertSame(CapsuleVersionState::InReview, $version->refresh()->state);
        $this->assertSame(1, $version->lock_version);
        $this->assertNull($version->content_digest);
        $this->assertNull($version->published_at);
        $this->assertNull($version->reviewer_id);
        foreach (['capsule_version_reviews', 'content_revisions', 'api_idempotency', 'notification_outbox'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_delivery_failure_keeps_committed_publication_and_retryable_intention(): void
    {
        [, $publisher, $capsule, $version] = $this->fixture();
        $this->publish($publisher, $capsule, $version);
        DB::unprepared("CREATE FUNCTION reject_b25_delivery_fixture() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''échec fictif livraison''; END'; CREATE TRIGGER reject_b25_delivery_fixture BEFORE INSERT ON internal_notifications FOR EACH ROW EXECUTE FUNCTION reject_b25_delivery_fixture()");
        $refused = false;
        try {
            app(NotificationOutbox::class)->deliver();
        } catch (NotificationStorageFailed) {
            $refused = true;
        } finally {
            DB::unprepared('DROP TRIGGER reject_b25_delivery_fixture ON internal_notifications; DROP FUNCTION reject_b25_delivery_fixture()');
        }
        $this->assertTrue($refused);
        $this->assertSame(CapsuleVersionState::Published, $version->refresh()->state);
        $this->assertNotNull($version->content_digest);
        $this->assertDatabaseCount('capsule_version_reviews', 1);
        $this->assertDatabaseCount('content_revisions', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->assertDatabaseHas('notification_outbox', ['kind' => 'capsule.version.published', 'delivered_at' => null]);
        $this->assertDatabaseCount('internal_notifications', 0);
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
    }

    public function test_replay_checks_current_publisher_rights_visibility_and_withdrawal(): void
    {
        [, $publisher, $capsule, $version] = $this->fixture();
        $key = new IdempotencyKey((string) Str::uuid());
        $this->publish($publisher, $capsule, $version, $key);
        foreach ([['role' => Role::Member], ['role' => Role::Moderator, 'email_verified_at' => null], ['email_verified_at' => now(), 'status' => AccountStatus::Suspended]] as $change) {
            $publisher->forceFill($change)->save();
            $refused = false;
            try {
                $this->publish($publisher, $capsule, $version, $key);
            } catch (AuthorizationException) {
                $refused = true;
            }
            $this->assertTrue($refused);
        }
        $publisher->forceFill(['role' => Role::Moderator, 'email_verified_at' => now(), 'status' => AccountStatus::Active])->save();
        $capsule->forceFill(['visibility' => CapsuleVisibility::Hidden])->save();
        $refused = false;
        try {
            $this->publish($publisher, $capsule, $version, $key);
        } catch (AuthorizationException) {
            $refused = true;
        }
        $this->assertTrue($refused);
        $capsule->forceFill(['visibility' => CapsuleVisibility::Visible])->save();
        $version->forceFill(['state' => CapsuleVersionState::Withdrawn, 'lock_version' => 3])->save();
        $refused = false;
        try {
            $this->publish($publisher, $capsule, $version, $key);
        } catch (StaleCapsuleVersion) {
            $refused = true;
        }
        $this->assertTrue($refused);
        foreach (['capsule_version_reviews', 'content_revisions', 'api_idempotency', 'notification_outbox'] as $table) {
            $this->assertDatabaseCount($table, 1);
        }
    }

    public function test_owner_is_not_replaced_by_a_documentation_contributor_and_old_notes_stay_private(): void
    {
        [$owner, $publisher, $capsule, $version] = $this->fixture();
        $writer = User::factory()->verified()->create();
        CapsuleContributor::factory()->create(['version_id' => $version->id, 'user_id' => $writer->id, 'contribution_role' => ContributionRole::Documentation]);
        $note = 'Ancienne note fictive de revue conservée, sans projection dans la notification.';
        $history = (new CapsuleVersionReview)->forceFill(['version_id' => $version->id, 'reviewer_id' => $publisher->id, 'decision' => ReviewDecision::RequestChanges, 'reviewed_lock_version' => null, 'note' => $note, 'created_at' => now()->utc()]);
        $history->save();
        $this->publish($publisher, $capsule, $version);
        $this->assertDatabaseCount('capsule_version_reviews', 2);
        $this->assertDatabaseHas('capsule_version_reviews', ['id' => $history->id, 'note' => $note, 'reviewed_lock_version' => null]);
        $this->assertDatabaseHas('notification_outbox', ['recipient_id' => $owner->id, 'kind' => 'capsule.version.published']);
        $this->assertSame(0, DB::table('notification_outbox')->whereIn('recipient_id', [$writer->id, $publisher->id])->count());
        app(NotificationOutbox::class)->deliver();
        $this->login($writer);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 0);
        $this->login($owner);
        $content = $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString($note, $content);
    }

    public function test_notification_downgrade_refuses_stable_events_before_any_constraint_changes(): void
    {
        [, $publisher, $capsule, $version] = $this->fixture();
        $this->publish($publisher, $capsule, $version);
        app(NotificationOutbox::class)->deliver();
        $before = [$this->kindCheck('notification_outbox'), $this->kindCheck('internal_notifications')];
        $migration = require base_path('database/migrations/2026_10_11_010000_b25_publication_notification_events.php');
        $failure = null;
        try {
            $migration->down();
        } catch (RuntimeException $e) {
            $failure = $e;
        }
        $this->assertInstanceOf(RuntimeException::class, $failure);
        $this->assertStringContainsString('événements de publication à conserver', $failure->getMessage());
        $this->assertSame($before, [$this->kindCheck('notification_outbox'), $this->kindCheck('internal_notifications')]);
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('internal_notifications', 1);
        $this->assertDatabaseCount('capsule_version_reviews', 1);
    }

    public function test_additive_type_down_and_up_preserve_another_installed_sql_type(): void
    {
        $owner = User::factory()->verified()->create();
        $migration = require base_path('database/migrations/2026_10_11_010000_b25_publication_notification_events.php');
        $migration->down();
        foreach (['notification_outbox', 'internal_notifications'] as $table) {
            $check = $this->kindCheck($table);
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_kind");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_kind CHECK (({$check}) OR kind = 'proposal.created')");
        }
        try {
            $migration->up();
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                $this->insertSqlEvent($table, $owner, 'proposal.created');
            }
            $migration->down();
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                $this->insertSqlEvent($table, $owner, 'proposal.created');
                $this->assertSame(2, DB::table($table)->where('kind', 'proposal.created')->count());
                $refused = false;
                try {
                    $this->insertSqlEvent($table, $owner, 'capsule.version.published');
                } catch (QueryException $e) {
                    $refused = $e->getCode() === '23514';
                }
                $this->assertTrue($refused);
            }
            $migration->up();
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                $this->insertSqlEvent($table, $owner, 'capsule.version.published');
                $this->assertSame(1, DB::table($table)->where('kind', 'capsule.version.published')->count());
                $this->assertSame(2, DB::table($table)->where('kind', 'proposal.created')->count());
            }
        } finally {
            // Retirer uniquement la fixture de type futur avant le rollback B24.
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                DB::table($table)->where('kind', 'proposal.created')->delete();
            }
        }
    }

    private function kindCheck(string $table): string
    {
        return DB::scalar('SELECT pg_get_expr(conbin, conrelid) FROM pg_constraint WHERE conrelid = CAST(? AS regclass) AND conname = ?', [$table, $table.'_kind']);
    }

    private function insertSqlEvent(string $table, User $owner, string $kind): void
    {
        DB::table($table)->insert(['id' => (string) Str::uuid(), 'event_id' => (string) Str::uuid(), 'recipient_id' => $owner->id, 'kind' => $kind, 'created_at' => now()->utc()]);
    }

    /** @return array{User, User, Capsule, CapsuleVersion} */
    private function fixture(?User $owner = null): array
    {
        $owner ??= User::factory()->verified()->create();
        $publisher = User::factory()->verified()->create(['role' => Role::Moderator]);
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id]);
        $version = CapsuleVersion::factory()->create(['capsule_id' => $capsule->id, 'state' => CapsuleVersionState::InReview]);

        return [$owner, $publisher, $capsule, $version];
    }

    private function publish(User $publisher, Capsule $capsule, CapsuleVersion $version, ?IdempotencyKey $key = null): CapsuleVersion
    {
        return app(PublishCapsuleVersionService::class)->handle($publisher, $capsule, $version, new PublishCommandData(1), $key ?? new IdempotencyKey((string) Str::uuid()));
    }

    private function login(User $actor): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $actor->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}
