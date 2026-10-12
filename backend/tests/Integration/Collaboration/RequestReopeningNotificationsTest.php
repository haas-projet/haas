<?php

namespace Tests\Integration\Collaboration;

use App\Data\HelpRequests\ReopeningData;
use App\Data\HelpRequests\ResolutionData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Notifications\NotificationEvent;
use App\Exceptions\NotificationStorageFailed;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\RequestReopening;
use App\Models\Resolution;
use App\Models\User;
use App\Services\HelpRequests\ReopenHelpRequestService;
use App\Services\HelpRequests\ResolveHelpRequestService;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\RequestReopeningFixtures;
use Tests\Support\SpaHttpRequests;

final class RequestReopeningNotificationsTest extends PostgresTestCase
{
    use DatabaseMigrations, RequestReopeningFixtures, SpaHttpRequests;

    private const string KEY = 'b2000000-2222-4444-8888-123456789abc';

    private const string REASON = 'Motif privé fictif B20 qui ne figure jamais dans une notification.';

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
            $this->removeReopeningFixtures($this->parentIds);
        } finally {
            parent::tearDown();
        }
    }

    public function test_transaction_observer_sees_no_revoke_fact_audit_or_intention_before_commit_then_delivery_once(): void
    {
        [$owner, $author, $parent, , $resolution] = $this->fixture();
        $service = app(ReopenHelpRequestService::class);
        $outbox = app(NotificationOutbox::class);
        $data = new ReopeningData(['lock_version' => 4, 'reason' => self::REASON]);
        $key = new IdempotencyKey(self::KEY);
        config(['database.connections.b20_notification_observer' => config('database.connections.pgsql')]);
        DB::beginTransaction();
        try {
            $fact = $service->reopen($owner, $parent->id, $data, $key);
            $this->assertDatabaseHas('notification_outbox', ['kind' => 'request.reopened', 'event_id' => $fact->id, 'recipient_id' => $author->id]);
            $observer = DB::connection('b20_notification_observer');
            $this->assertSame(0, $observer->table('request_reopenings')->where('id', $fact->id)->count());
            $this->assertSame(0, $observer->table('notification_outbox')->where('event_id', $fact->id)->count());
            $this->assertSame(0, $observer->table('content_revisions')->count());
            $this->assertSame(0, $observer->table('api_idempotency')->count());
            $this->assertNull($observer->table('resolutions')->where('id', $resolution->id)->value('revoked_at'));
            $this->assertSame('resolved', $observer->table('help_requests')->where('id', $parent->id)->value('state'));
            $this->assertSame(4, $observer->table('help_requests')->where('id', $parent->id)->value('lock_version'));
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
            DB::disconnect('b20_notification_observer');
        }
        foreach (['request_reopenings', 'content_revisions', 'api_idempotency', 'notification_outbox', 'internal_notifications'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertNull($resolution->refresh()->revoked_at);
        $this->assertSame('resolved', $parent->refresh()->state->value);
        $fact = $service->reopen($owner, $parent->id, $data, $key);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseCount('internal_notifications', 0);
        $this->assertSame(1, $outbox->deliver());
        DB::transaction(fn () => $outbox->record(new NotificationEvent($fact->id, $author->id, 'request.reopened')));
        $this->assertSame(0, $outbox->deliver());
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('internal_notifications', 1);
        $this->login($author);
        $inbox = $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.kind', 'request.reopened')->assertJsonPath('data.0.message', 'L’auteur a rouvert la demande liée à votre proposition.')
            ->assertJsonPath('data.0.target_path', '/requests/'.$parent->id);
        foreach ([self::REASON, 'Extrait privé fictif', 'code privé fictif B20', $owner->email, $author->email] as $private) {
            $this->assertStringNotContainsString($private, (string) $inbox->getContent());
        }
        $this->assertEqualsCanonicalizing(['id', 'kind', 'message', 'target_path', 'read_at', 'created_at'], array_keys($inbox->json('data.0')));
    }

    #[DataProvider('visibilityChanges')]
    public function test_current_visibility_precedes_list_total_unread_and_mark(string $kind): void
    {
        [$owner, $author, $parent, $proposal] = $this->fixture();
        $fact = $this->reopen($owner, $parent);
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $notificationId = (string) DB::table('internal_notifications')->where('event_id', $fact->id)->value('id');
        $this->login($author);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1);
        match ($kind) {
            'proposal_hidden' => $proposal->forceFill(['hidden_at' => now()])->save(),
            'parent_hidden' => $parent->forceFill(['hidden_at' => now()])->save(),
            'owner_suspended' => $owner->forceFill(['status' => 'suspended'])->save(),
            'owner_unverified' => $owner->forceFill(['email_verified_at' => null])->save(),
            'owner_changed' => $parent->forceFill(['author_id' => User::factory()->verified()->create()->id])->save(),
            'proposal_author_changed' => $proposal->forceFill(['author_id' => User::factory()->verified()->create()->id])->save(),
            default => throw new \InvalidArgumentException('Fixture de visibilité B20 inconnue.'),
        };
        $this->assertUnavailable($notificationId);
        $this->assertDatabaseCount('request_reopenings', 1);
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
    }

    public function test_reopening_notification_remains_a_visible_historical_fact_after_a_new_acceptance(): void
    {
        [$owner, $author, $parent] = $this->fixture();
        $fact = $this->reopen($owner, $parent);
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $id = (string) DB::table('internal_notifications')->where('event_id', $fact->id)->value('id');
        $new = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => User::factory()->verified()]);
        app(ResolveHelpRequestService::class)->resolve($owner, $parent->id, new ResolutionData([
            'proposal_id' => $new->id, 'lock_version' => 5, 'proposal_lock_version' => 1,
            'environment' => 'Nouveau contexte fictif.', 'validation_note' => 'Une nouvelle acceptation sans effacer la réouverture précédente.',
        ]), new IdempotencyKey((string) Str::uuid()));
        $this->assertSame('resolved', $parent->refresh()->state->value);
        $this->login($author);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.kind', 'request.reopened');
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$id, ['read' => true])->assertOk();
        $this->assertDatabaseCount('request_reopenings', 1);
        $this->assertSame(2, DB::table('resolutions')->where('request_id', $parent->id)->count());
    }

    /** @return iterable<string, array{string}> */
    public static function visibilityChanges(): iterable
    {
        foreach (['proposal_hidden', 'parent_hidden', 'owner_suspended', 'owner_unverified', 'owner_changed', 'proposal_author_changed'] as $kind) {
            yield $kind => [$kind];
        }
    }

    public function test_forged_recipient_is_never_projected_and_suspended_or_unverified_recipient_loses_projection(): void
    {
        [$owner, $author, $parent] = $this->fixture();
        $fact = $this->reopen($owner, $parent);
        $stranger = User::factory()->verified()->create();
        DB::transaction(fn () => app(NotificationOutbox::class)->record(new NotificationEvent($fact->id, $stranger->id, 'request.reopened')));
        $this->assertSame(2, app(NotificationOutbox::class)->deliver());
        $forgedId = (string) DB::table('internal_notifications')->where('event_id', $fact->id)->where('recipient_id', $stranger->id)->value('id');
        $this->login($stranger);
        $this->assertUnavailable($forgedId);
        $this->login($author);
        $legitimateId = (string) DB::table('internal_notifications')->where('event_id', $fact->id)->where('recipient_id', $author->id)->value('id');
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1);
        $author->forceFill(['email_verified_at' => null])->save();
        $this->assertUnavailable($legitimateId);
        $author->forceFill(['status' => 'suspended'])->save();
        $this->browserRequest('GET', '/api/v1/notifications')->assertForbidden();
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$legitimateId, ['read' => true])->assertUnauthorized();
    }

    public function test_worker_failure_preserves_reopening_and_pending_intention_for_retry(): void
    {
        [$owner, , $parent] = $this->fixture();
        $fact = $this->reopen($owner, $parent);
        DB::statement('ALTER TABLE internal_notifications ADD CONSTRAINT fixture_b20_delivery_failure CHECK (false)');
        try {
            $refused = false;
            try {
                app(NotificationOutbox::class)->deliver();
            } catch (NotificationStorageFailed) {
                $refused = true;
            }
            $this->assertTrue($refused);
            $this->assertDatabaseHas('request_reopenings', ['id' => $fact->id, 'reason' => self::REASON]);
            $this->assertSame('open', $parent->refresh()->state->value);
            $this->assertSame(5, $parent->lock_version);
            $this->assertDatabaseHas('notification_outbox', ['event_id' => $fact->id, 'delivered_at' => null]);
            $this->assertDatabaseCount('internal_notifications', 0);
        } finally {
            DB::statement('ALTER TABLE internal_notifications DROP CONSTRAINT fixture_b20_delivery_failure');
        }
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
    }

    /** @return array{User, User, HelpRequest, Proposal, Resolution} */
    private function fixture(): array
    {
        $owner = User::factory()->verified()->create();
        $author = User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'resolved', 'lock_version' => 4]);
        $this->parentIds[] = $parent->id;
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $author->id, 'state' => 'accepted', 'lock_version' => 7, 'code' => '// code privé fictif B20']);
        $resolution = Resolution::factory()->create(['request_id' => $parent->id, 'proposal_id' => $proposal->id,
            'accepted_by' => $owner->id, 'proposal_version' => 7, 'environment' => 'Contexte privé fictif.', 'validation_note' => 'Extrait privé fictif de la décision historique.', 'accepted_at' => now()->subMinute()]);

        return [$owner, $author, $parent, $proposal, $resolution];
    }

    private function reopen(User $owner, HelpRequest $parent): RequestReopening
    {
        return app(ReopenHelpRequestService::class)->reopen($owner, $parent->id, new ReopeningData(['lock_version' => 4, 'reason' => self::REASON]), new IdempotencyKey(self::KEY));
    }

    private function assertUnavailable(string $id): void
    {
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('unread_count', 0)->assertJsonCount(0, 'data');
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$id, ['read' => true])->assertNotFound();
        $this->assertDatabaseHas('internal_notifications', ['id' => $id, 'read_at' => null]);
    }

    private function login(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}
