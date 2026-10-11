<?php

namespace Tests\Integration\Collaboration;

use App\Data\Collaboration\ProposalData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Notifications\NotificationEvent;
use App\Exceptions\NotificationStorageFailed;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Technology;
use App\Models\User;
use App\Services\Collaboration\ProposalService;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;
use Tests\Support\TestDatabaseGuard;

final class ProposalNotificationsTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    private const string KEY = 'b1800000-1111-4444-8888-123456789abc';

    private const string DIAGNOSIS = 'Diagnostic fictif privé à ne pas recopier dans une notification.';

    private const string CODE = "  // Code fictif privé, pas un extrait de notification.\r\n";

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
            // Nettoyage limité aux demandes et événements créés par ces tests.
            // Le down de production continue de refuser toute perte de contenu B18.
            if ($this->parentIds !== []) {
                $this->assertSame('testing', config('app.env'));
                $driver = config('database.default');
                $this->assertIsString($driver);
                $connection = config('database.connections.'.$driver);
                $this->assertIsArray($connection);
                TestDatabaseGuard::check('testing', $driver, $connection);
                $ids = DB::table('proposals')->whereIn('request_id', $this->parentIds)->pluck('id')->all();
                foreach (['internal_notifications', 'notification_outbox'] as $table) {
                    DB::table($table)->where('kind', 'proposal.created')->whereIn('event_id', $ids)->delete();
                }
                DB::table('proposal_revisions')->whereIn('proposal_id', $ids)->delete();
                DB::table('proposals')->whereIn('id', $ids)->delete();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_event_and_parent_transition_are_atomic_and_delivery_occurs_once_after_commit(): void
    {
        $parent = $this->parentFixture();
        $actor = User::factory()->verified()->create();
        $service = app(ProposalService::class);
        $outbox = app(NotificationOutbox::class);
        $data = new ProposalData(self::payload());
        $key = new IdempotencyKey(self::KEY);
        config(['database.connections.b18_notification_observer' => config('database.connections.pgsql')]);

        DB::beginTransaction();
        try {
            $rolledBack = $service->create($actor, $parent->id, $data, $key);
            $this->assertDatabaseHas('notification_outbox', ['event_id' => $rolledBack->id, 'recipient_id' => $parent->author_id, 'kind' => 'proposal.created', 'delivered_at' => null]);
            $observer = DB::connection('b18_notification_observer');
            $this->assertSame(0, $observer->table('proposals')->where('id', $rolledBack->id)->count());
            $this->assertSame(0, $observer->table('proposal_revisions')->where('proposal_id', $rolledBack->id)->count());
            $this->assertSame(0, $observer->table('notification_outbox')->where('event_id', $rolledBack->id)->count());
            $this->assertSame('open', $observer->table('help_requests')->where('id', $parent->id)->value('state'));
            $this->assertSame(1, $observer->table('help_requests')->where('id', $parent->id)->value('lock_version'));
            try {
                $outbox->deliver();
                $this->fail('La livraison doit refuser une transaction métier encore ouverte.');
            } catch (LogicException) {
                $this->assertDatabaseCount('internal_notifications', 0);
            }
        } finally {
            DB::rollBack();
            DB::disconnect('b18_notification_observer');
        }
        foreach (['proposals', 'proposal_revisions', 'content_revisions', 'api_idempotency', 'notification_outbox', 'internal_notifications'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame('open', $parent->refresh()->state->value);
        $this->assertSame(1, $parent->lock_version);

        $proposal = $service->create($actor, $parent->id, $data, $key);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'diagnosis' => self::DIAGNOSIS, 'code' => self::CODE]);
        $this->assertDatabaseCount('content_revisions', 2);
        $this->assertDatabaseCount('internal_notifications', 0);
        $this->assertSame(1, $outbox->deliver());
        $this->assertSame(0, DB::transactionLevel());
        $this->assertNotNull(DB::table('notification_outbox')->where('event_id', $proposal->id)->value('delivered_at'));
        $this->assertDatabaseHas('internal_notifications', ['event_id' => $proposal->id, 'recipient_id' => $parent->author_id, 'kind' => 'proposal.created']);
        DB::transaction(fn () => $outbox->record(new NotificationEvent($proposal->id, $parent->author_id, 'proposal.created')));
        $this->assertSame(0, $outbox->deliver());
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('internal_notifications', 1);

        $this->login(User::findOrFail($parent->author_id));
        $inbox = $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.kind', 'proposal.created')->assertJsonPath('data.0.message', 'Une proposition a été ajoutée à votre demande.')
            ->assertJsonPath('data.0.target_path', '/requests/'.$parent->id);
        $this->assertEqualsCanonicalizing(['id', 'kind', 'message', 'target_path', 'read_at', 'created_at'], array_keys($inbox->json('data.0')));
        $content = $inbox->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString(self::DIAGNOSIS, $content);
        $this->assertStringNotContainsString(self::CODE, $content);
        $this->assertStringNotContainsString('Code fictif privé', $content);
        $this->assertStringNotContainsString($actor->email, $content);
    }

    public function test_self_proposals_and_edits_do_not_schedule_new_notifications(): void
    {
        $parent = $this->parentFixture();
        $owner = User::findOrFail($parent->author_id);
        $selfId = $this->createProposal($owner, $parent);
        $this->assertDatabaseCount('notification_outbox', 0);
        $actor = User::factory()->verified()->create();
        $id = $this->createProposal($actor, $parent);
        $this->browserRequest('PATCH', '/api/v1/proposals/'.$id, ['fix' => 'Le correctif est clarifié après un premier retour.', 'lock_version' => 1], ['Idempotency-Key' => self::KEY])->assertOk();
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseMissing('notification_outbox', ['event_id' => $selfId]);
        $this->assertDatabaseHas('notification_outbox', ['event_id' => $id, 'recipient_id' => $owner->id, 'kind' => 'proposal.created']);
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
    }

    #[DataProvider('currentVisibilityChanges')]
    public function test_current_visibility_precedes_inbox_total_unread_count_marking_and_create_replay(string $kind): void
    {
        $parent = $this->parentFixture();
        $actor = User::factory()->verified()->create();
        $id = $this->createProposal($actor, $parent);
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $notificationId = (string) DB::table('internal_notifications')->where('event_id', $id)->value('id');
        $owner = User::findOrFail($parent->author_id);
        $this->login($owner);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1);
        match ($kind) {
            'proposal_hidden' => Proposal::whereKey($id)->update(['hidden_at' => now()]),
            'parent_hidden' => $parent->forceFill(['hidden_at' => now()])->save(),
            'draft_parent' => $parent->forceFill(['state' => 'draft'])->save(),
            'suspended_author' => $actor->forceFill(['status' => 'suspended'])->save(),
            'unverified_author' => $actor->forceFill(['email_verified_at' => null])->save(),
            default => throw new \InvalidArgumentException('Fixture de visibilité B18 inconnue.'),
        };
        $this->assertUnavailableInInbox($notificationId);
        $this->assertDatabaseHas('internal_notifications', ['id' => $notificationId, 'recipient_id' => $owner->id, 'event_id' => $id, 'kind' => 'proposal.created']);
        if (! in_array($kind, ['suspended_author', 'unverified_author'], true)) {
            $this->login($actor);
            $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/proposals', self::payload(), ['Idempotency-Key' => self::KEY])->assertNotFound();
        }
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('proposals', 1);
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
    }

    /** @return iterable<string, array{string}> */
    public static function currentVisibilityChanges(): iterable
    {
        foreach (['proposal_hidden', 'parent_hidden', 'draft_parent', 'suspended_author', 'unverified_author'] as $kind) {
            yield $kind => [$kind];
        }
    }

    #[DataProvider('ineligibleRecipients')]
    public function test_recipient_must_remain_currently_active_and_verified(string $kind): void
    {
        $parent = $this->parentFixture();
        $id = $this->createProposal(User::factory()->verified()->create(), $parent);
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $notificationId = (string) DB::table('internal_notifications')->where('event_id', $id)->value('id');
        $owner = User::findOrFail($parent->author_id);
        $this->login($owner);
        $owner->forceFill($kind === 'suspended' ? ['status' => 'suspended'] : ['email_verified_at' => null])->save();
        if ($kind === 'suspended') {
            $this->browserRequest('GET', '/api/v1/notifications')->assertForbidden();
            // Le middleware termine réellement la session du compte suspendu.
            $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertUnauthorized();
        } else {
            // La boîte reste accessible à un compte actif ; le parent non vérifié retire sa proposition.
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

    public function test_forged_recipient_event_is_never_projected_or_marked_by_stranger(): void
    {
        $parent = $this->parentFixture();
        $id = $this->createProposal(User::factory()->verified()->create(), $parent);
        $stranger = User::factory()->verified()->create();
        DB::transaction(fn () => app(NotificationOutbox::class)->record(new NotificationEvent($id, $stranger->id, 'proposal.created')));
        $this->assertSame(2, app(NotificationOutbox::class)->deliver());
        $forgedId = (string) DB::table('internal_notifications')->where('event_id', $id)->where('recipient_id', $stranger->id)->value('id');
        $this->login($stranger);
        $this->assertUnavailableInInbox($forgedId);
        $this->login(User::findOrFail($parent->author_id));
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('unread_count', 1);
    }

    public function test_worker_failure_keeps_committed_business_and_pending_intention_for_retry(): void
    {
        $parent = $this->parentFixture();
        $id = $this->createProposal(User::factory()->verified()->create(), $parent);
        DB::statement('ALTER TABLE internal_notifications ADD CONSTRAINT fixture_b18_delivery_failure CHECK (false)');
        try {
            $rejected = false;
            try {
                app(NotificationOutbox::class)->deliver();
            } catch (NotificationStorageFailed) {
                $rejected = true;
            }
            $this->assertTrue($rejected, 'Le refus du stockage doit remonter sans perdre l’intention.');
            $this->assertDatabaseHas('proposals', ['id' => $id]);
            $this->assertSame('in_progress', $parent->refresh()->state->value);
            $this->assertSame(2, $parent->lock_version);
            $this->assertDatabaseCount('proposal_revisions', 1);
            $this->assertDatabaseCount('content_revisions', 2);
            $this->assertDatabaseCount('api_idempotency', 1);
            $this->assertDatabaseCount('internal_notifications', 0);
            $this->assertDatabaseHas('notification_outbox', ['event_id' => $id, 'delivered_at' => null]);
        } finally {
            DB::statement('ALTER TABLE internal_notifications DROP CONSTRAINT fixture_b18_delivery_failure');
        }
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
    }

    /** @return array{diagnosis: string, fix: string, verification: string, limits: string, code: string} */
    private static function payload(): array
    {
        return [
            'diagnosis' => self::DIAGNOSIS,
            'fix' => 'Correctif décrit avec des étapes et prérequis précis.',
            'verification' => 'Vérification décrite avec des observations reproductibles.',
            'limits' => 'Limites annoncées avant toute adoption de ce correctif.',
            'code' => self::CODE,
        ];
    }

    private function assertUnavailableInInbox(string $notificationId): void
    {
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('unread_count', 0)->assertJsonCount(0, 'data');
        $this->browserRequest('PATCH', '/api/v1/notifications/'.$notificationId, ['read' => true])->assertNotFound();
        $this->assertDatabaseHas('internal_notifications', ['id' => $notificationId, 'read_at' => null]);
    }

    private function createProposal(User $actor, HelpRequest $parent): string
    {
        $this->login($actor);

        return (string) $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/proposals', self::payload(), ['Idempotency-Key' => self::KEY])->assertCreated()->json('data.id');
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
