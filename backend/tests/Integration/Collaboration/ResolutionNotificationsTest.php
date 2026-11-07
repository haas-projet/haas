<?php

namespace Tests\Integration\Collaboration;

use App\Data\HelpRequests\ResolutionData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Notifications\NotificationEvent;
use App\Exceptions\NotificationStorageFailed;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\Technology;
use App\Models\User;
use App\Services\HelpRequests\ResolveHelpRequestService;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;
use Tests\Support\TestDatabaseGuard;

final class ResolutionNotificationsTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    private const string KEY = 'b1900000-1111-4444-8888-123456789abc';

    private const string NOTE = 'Validation privee fictive B19 : le resultat est declare dans mon contexte.';

    private const string ENVIRONMENT = 'Environnement prive fictif B19 avec PostgreSQL 17';

    private const string CODE = "  // Code prive fictif qui ne figure jamais dans une notification.\r\n";

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
            // Ces UUID appartiennent aux seules fixtures créées dans cette classe.
            // Le down de production conserve son refus de perdre les données B19.
            if ($this->parentIds !== []) {
                $this->assertSame('testing', config('app.env'));
                $driver = config('database.default');
                $this->assertIsString($driver);
                $connection = config('database.connections.'.$driver);
                $this->assertIsArray($connection);
                TestDatabaseGuard::check('testing', $driver, $connection);
                $ids = DB::table('resolutions')->whereIn('request_id', $this->parentIds)->pluck('id')->all();
                foreach (['internal_notifications', 'notification_outbox'] as $table) {
                    DB::table($table)->where('kind', 'resolution.accepted')->whereIn('event_id', $ids)->delete();
                }
                DB::table('resolutions')->whereIn('id', $ids)->delete();
                $proposalIds = DB::table('proposals')->whereIn('request_id', $this->parentIds)->pluck('id')->all();
                DB::table('proposal_revisions')->whereIn('proposal_id', $proposalIds)->delete();
                DB::table('proposals')->whereIn('id', $proposalIds)->delete();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_resolution_parent_proposal_audits_and_event_commit_together_then_delivery_is_deduplicated(): void
    {
        [$owner, $author, $parent, $proposal] = $this->fixture();
        $parentBefore = $parent->refresh()->getAttributes();
        $proposalBefore = $proposal->refresh()->getAttributes();
        $service = app(ResolveHelpRequestService::class);
        $outbox = app(NotificationOutbox::class);
        $data = new ResolutionData(self::payload($parent, $proposal));
        $key = new IdempotencyKey(self::KEY);
        config(['database.connections.b19_notification_observer' => config('database.connections.pgsql')]);

        DB::beginTransaction();
        try {
            $rolledBack = $service->resolve($owner, $parent->id, $data, $key);
            $this->assertDatabaseHas('notification_outbox', ['event_id' => $rolledBack->id, 'recipient_id' => $author->id, 'kind' => 'resolution.accepted', 'delivered_at' => null]);
            $this->assertDatabaseCount('content_revisions', 2);
            $this->assertDatabaseCount('api_idempotency', 1);
            $observer = DB::connection('b19_notification_observer');
            $this->assertSame(0, $observer->table('resolutions')->where('id', $rolledBack->id)->count());
            $this->assertSame(0, $observer->table('notification_outbox')->where('event_id', $rolledBack->id)->count());
            $this->assertSame(0, $observer->table('content_revisions')->count());
            $this->assertSame(0, $observer->table('api_idempotency')->count());
            $this->assertSame('in_progress', $observer->table('help_requests')->where('id', $parent->id)->value('state'));
            $this->assertSame(4, $observer->table('help_requests')->where('id', $parent->id)->value('lock_version'));
            $this->assertSame('proposed', $observer->table('proposals')->where('id', $proposal->id)->value('state'));
            $this->assertSame(3, $observer->table('proposals')->where('id', $proposal->id)->value('lock_version'));
            $refused = false;
            try {
                $outbox->deliver();
            } catch (LogicException) {
                $refused = true;
            }
            $this->assertTrue($refused, 'La livraison doit refuser une transaction métier encore ouverte.');
            $this->assertDatabaseCount('internal_notifications', 0);
        } finally {
            DB::rollBack();
            DB::disconnect('b19_notification_observer');
        }
        foreach (['resolutions', 'content_revisions', 'api_idempotency', 'notification_outbox', 'internal_notifications'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame($parentBefore, $parent->refresh()->getAttributes());
        $this->assertSame($proposalBefore, $proposal->refresh()->getAttributes());

        $resolution = $service->resolve($owner, $parent->id, $data, $key);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseHas('resolutions', ['id' => $resolution->id, 'environment' => self::ENVIRONMENT, 'validation_note' => self::NOTE, 'proposal_version' => 3]);
        $this->assertSame('resolved', $parent->refresh()->state->value);
        $this->assertSame(5, $parent->lock_version);
        $this->assertSame('accepted', $proposal->refresh()->state->value);
        $this->assertSame(3, $proposal->lock_version);
        $this->assertDatabaseCount('proposal_revisions', 0);
        $this->assertDatabaseCount('content_revisions', 2);
        $this->assertDatabaseCount('internal_notifications', 0);
        $this->assertSame(1, $outbox->deliver());
        $this->assertSame(0, DB::transactionLevel());
        $this->assertNotNull(DB::table('notification_outbox')->where('event_id', $resolution->id)->value('delivered_at'));
        $this->assertDatabaseHas('internal_notifications', ['event_id' => $resolution->id, 'recipient_id' => $author->id, 'kind' => 'resolution.accepted']);
        DB::transaction(fn () => $outbox->record(new NotificationEvent($resolution->id, $author->id, 'resolution.accepted')));
        $this->assertSame(0, $outbox->deliver());
        $this->assertSame($resolution->id, $service->resolve($owner, $parent->id, $data, $key)->id);
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('internal_notifications', 1);
        $this->assertDatabaseCount('resolutions', 1);
        $this->assertDatabaseCount('content_revisions', 2);
        $this->assertDatabaseCount('api_idempotency', 1);

        $this->login($author);
        $inbox = $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.kind', 'resolution.accepted')->assertJsonPath('data.0.message', 'L’auteur a accepté votre proposition dans son contexte.')
            ->assertJsonPath('data.0.target_path', '/requests/'.$parent->id);
        $this->assertEqualsCanonicalizing(['id', 'kind', 'message', 'target_path', 'read_at', 'created_at'], array_keys($inbox->json('data.0')));
        $content = $inbox->getContent();
        $this->assertIsString($content);
        foreach ([self::NOTE, self::ENVIRONMENT, 'Code prive fictif', $owner->email, $author->email] as $private) {
            $this->assertStringNotContainsString($private, $content);
        }
        foreach (['content_revisions', 'notification_outbox', 'api_idempotency'] as $table) {
            $serialized = json_encode(DB::table($table)->get()->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString(self::NOTE, $serialized);
            $this->assertStringNotContainsString(self::ENVIRONMENT, $serialized);
            $this->assertStringNotContainsString('Code prive fictif', $serialized);
        }
        $notificationId = (string) $inbox->json('data.0.id');
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertOk();
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 0);
        $this->login($owner);
        $this->assertUnavailableInInbox($notificationId, preserveUnread: false);
    }

    public function test_self_resolution_has_no_intention_or_delivery(): void
    {
        [$owner, , $parent, $proposal] = $this->fixture(selfResolution: true);
        $resolution = $this->resolve($owner, $parent, $proposal);
        $this->assertSame($owner->id, $resolution->accepted_by);
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
        $this->assertDatabaseCount('internal_notifications', 0);
        $this->assertDatabaseCount('content_revisions', 2);
        $this->login($owner);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('unread_count', 0)->assertJsonCount(0, 'data');
    }

    #[DataProvider('currentVisibilityChanges')]
    public function test_current_visibility_and_attribution_precede_list_count_unread_and_mark(string $kind): void
    {
        [$owner, $author, $parent, $proposal] = $this->fixture();
        $resolution = $this->resolve($owner, $parent, $proposal);
        $parent->refresh();
        $proposal->refresh();
        $this->assertSame('resolved', $parent->state->value);
        $this->assertSame('accepted', $proposal->state->value);
        $this->assertSame(5, $parent->lock_version);
        $this->assertSame(3, $proposal->lock_version);
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $notificationId = (string) DB::table('internal_notifications')->where('event_id', $resolution->id)->value('id');
        $this->login($author);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1);
        match ($kind) {
            'resolution_revoked' => $resolution->forceFill(['revoked_at' => now()])->save(),
            'parent_hidden' => $parent->forceFill(['hidden_at' => now()])->save(),
            'proposal_hidden' => $proposal->forceFill(['hidden_at' => now()])->save(),
            'parent_open' => $parent->forceFill(['state' => 'open'])->save(),
            'proposal_proposed' => $proposal->forceFill(['state' => 'proposed'])->save(),
            'proposal_not_selected' => $proposal->forceFill(['state' => 'not_selected'])->save(),
            'owner_suspended' => $owner->forceFill(['status' => 'suspended'])->save(),
            'owner_unverified' => $owner->forceFill(['email_verified_at' => null])->save(),
            'owner_changed' => $parent->forceFill(['author_id' => User::factory()->verified()->create()->id])->save(),
            'proposal_author_changed' => $proposal->forceFill(['author_id' => User::factory()->verified()->create()->id])->save(),
            'proposal_version_changed' => $proposal->forceFill(['lock_version' => 4])->save(),
            default => throw new \InvalidArgumentException('Fixture de visibilité B19 inconnue.'),
        };
        $this->assertUnavailableInInbox($notificationId);
        $this->assertDatabaseHas('internal_notifications', ['id' => $notificationId, 'recipient_id' => $author->id, 'event_id' => $resolution->id, 'kind' => 'resolution.accepted']);
        $this->assertDatabaseCount('resolutions', 1);
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
    }

    /** @return iterable<string, array{string}> */
    public static function currentVisibilityChanges(): iterable
    {
        foreach (['resolution_revoked', 'parent_hidden', 'proposal_hidden', 'parent_open', 'proposal_proposed', 'proposal_not_selected', 'owner_suspended', 'owner_unverified', 'owner_changed', 'proposal_author_changed', 'proposal_version_changed'] as $kind) {
            yield $kind => [$kind];
        }
    }

    #[DataProvider('ineligibleRecipients')]
    public function test_recipient_must_remain_currently_active_and_verified(string $kind): void
    {
        [$owner, $author, $parent, $proposal] = $this->fixture();
        $resolution = $this->resolve($owner, $parent, $proposal);
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $notificationId = (string) DB::table('internal_notifications')->where('event_id', $resolution->id)->value('id');
        $this->login($author);
        $author->forceFill($kind === 'suspended' ? ['status' => 'suspended'] : ['email_verified_at' => null])->save();
        if ($kind === 'suspended') {
            $this->browserRequest('GET', '/api/v1/notifications')->assertForbidden();
            $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertUnauthorized();
        } else {
            $this->assertUnavailableInInbox($notificationId);
        }
        $this->assertDatabaseHas('internal_notifications', ['id' => $notificationId, 'read_at' => null]);
    }

    /** @return iterable<string, array{string}> */
    public static function ineligibleRecipients(): iterable
    {
        yield 'suspended' => ['suspended'];
        yield 'unverified' => ['unverified'];
    }

    public function test_forged_recipient_intention_is_stored_but_never_projected_or_marked_by_stranger(): void
    {
        [$owner, $author, $parent, $proposal] = $this->fixture();
        $resolution = $this->resolve($owner, $parent, $proposal);
        $stranger = User::factory()->verified()->create();
        DB::transaction(fn () => app(NotificationOutbox::class)->record(new NotificationEvent($resolution->id, $stranger->id, 'resolution.accepted')));
        $this->assertSame(2, app(NotificationOutbox::class)->deliver());
        $forgedId = (string) DB::table('internal_notifications')->where('event_id', $resolution->id)->where('recipient_id', $stranger->id)->value('id');
        $this->login($stranger);
        $this->assertUnavailableInInbox($forgedId);
        $this->login($author);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1);
    }

    public function test_worker_failure_preserves_committed_resolution_and_pending_intention_for_retry(): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        $resolution = $this->resolve($owner, $parent, $proposal);
        DB::statement('ALTER TABLE internal_notifications ADD CONSTRAINT fixture_b19_delivery_failure CHECK (false)');
        try {
            $refused = false;
            try {
                app(NotificationOutbox::class)->deliver();
            } catch (NotificationStorageFailed) {
                $refused = true;
            }
            $this->assertTrue($refused, 'Le refus du stockage doit laisser l’intention disponible pour une nouvelle tentative.');
            $this->assertDatabaseHas('resolutions', ['id' => $resolution->id, 'environment' => self::ENVIRONMENT, 'validation_note' => self::NOTE]);
            $this->assertSame('resolved', $parent->refresh()->state->value);
            $this->assertSame(5, $parent->lock_version);
            $this->assertSame('accepted', $proposal->refresh()->state->value);
            $this->assertSame(3, $proposal->lock_version);
            $this->assertDatabaseCount('content_revisions', 2);
            $this->assertDatabaseCount('api_idempotency', 1);
            $this->assertDatabaseCount('internal_notifications', 0);
            $this->assertDatabaseHas('notification_outbox', ['event_id' => $resolution->id, 'delivered_at' => null]);
        } finally {
            DB::statement('ALTER TABLE internal_notifications DROP CONSTRAINT fixture_b19_delivery_failure');
        }
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
    }

    /** @return array{User, User, HelpRequest, Proposal} */
    private function fixture(bool $selfResolution = false): array
    {
        $owner = User::factory()->verified()->create();
        $author = $selfResolution ? $owner : User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'in_progress', 'lock_version' => 4]);
        $this->parentIds[] = $parent->id;
        $parent->technologies()->attach(Technology::factory()->create());
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $author->id, 'lock_version' => 3, 'code' => self::CODE]);

        return [$owner, $author, $parent, $proposal];
    }

    /** @return array{proposal_id: string, lock_version: int, proposal_lock_version: int, environment: string, validation_note: string} */
    private static function payload(HelpRequest $parent, Proposal $proposal): array
    {
        return ['proposal_id' => $proposal->id, 'lock_version' => $parent->lock_version, 'proposal_lock_version' => $proposal->lock_version,
            'environment' => self::ENVIRONMENT, 'validation_note' => self::NOTE];
    }

    private function resolve(User $owner, HelpRequest $parent, Proposal $proposal): Resolution
    {
        return app(ResolveHelpRequestService::class)->resolve($owner, $parent->id, new ResolutionData(self::payload($parent, $proposal)), new IdempotencyKey(self::KEY));
    }

    private function assertUnavailableInInbox(string $notificationId, bool $preserveUnread = true): void
    {
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('unread_count', 0)->assertJsonCount(0, 'data');
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertNotFound();
        if ($preserveUnread) {
            $this->assertDatabaseHas('internal_notifications', ['id' => $notificationId, 'read_at' => null]);
        }
    }

    private function login(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}
