<?php

namespace Tests\Integration\Collaboration;

use App\Data\HelpRequests\ResolutionData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\Identity\Role;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\User;
use App\Services\HelpRequests\ResolveHelpRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class RequestReopeningsTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    private const string KEY = 'b2000000-1111-4444-8888-123456789abc';

    private const string REASON = 'Motif prive fictif : le blocage réapparaît dans le contexte annoncé.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_owner_reopens_once_and_preserves_resolution_proposals_and_historical_versions(): void
    {
        [$owner, $author, $parent, $proposal, $resolution] = $this->fixture();
        $proposalBefore = $proposal->refresh()->getAttributes();
        $resolutionBefore = $resolution->refresh()->getAttributes();
        $other = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => User::factory()->verified(), 'lock_version' => 2]);
        $otherBefore = $other->refresh()->getAttributes();
        $this->login($owner);
        Http::preventStrayRequests();
        $payload = ['lock_version' => 4, 'reason' => self::REASON.' <script>texte inerte</script> ![image](https://example.test/no-fetch)'];
        $first = $this->browserRequest('POST', $this->path($parent), $payload, ['Idempotency-Key' => self::KEY])->assertOk()
            ->assertJsonPath('data.request_id', $parent->id)->assertJsonPath('data.resolution_id', $resolution->id)
            ->assertJsonPath('data.request_version_before', 4)->assertJsonPath('data.request_version_after', 5)
            ->assertJsonPath('data.reopened_by.id', $owner->id)->assertJsonPath('data.reopened_by.handle', $owner->handle)
            ->assertJsonPath('data.reason', $payload['reason'])->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonMissingPath('data.reopened_by.email')->assertJsonMissingPath('data.reopened_by.name');
        $this->assertStringNotContainsString('<script>', (string) $first->json('data.reason_html'));
        $this->assertStringNotContainsString('<img', (string) $first->json('data.reason_html'));
        Http::assertNothingSent();
        $this->browserRequest('POST', '/api/v1/requests/'.strtoupper($parent->id).'/reopen', $payload, ['Idempotency-Key' => strtoupper(self::KEY)])
            ->assertOk()->assertExactJson($first->json());
        $this->browserRequest('POST', $this->path($parent), $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
        $this->browserRequest('POST', $this->path($parent), array_replace($payload, ['reason' => 'Une autre intention de réouverture documentée.']), ['Idempotency-Key' => self::KEY])
            ->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame('open', $parent->refresh()->state->value);
        $this->assertSame(5, $parent->lock_version);
        $this->assertSame($proposalBefore, $proposal->refresh()->getAttributes());
        $this->assertSame($otherBefore, $other->refresh()->getAttributes());
        $currentResolution = $resolution->refresh()->getAttributes();
        $this->assertNotNull($resolution->revoked_at);
        foreach (['revoked_at', 'updated_at'] as $mutable) {
            unset($resolutionBefore[$mutable], $currentResolution[$mutable]);
        }
        $this->assertSame($resolutionBefore, $currentResolution);
        $this->assertDatabaseCount('request_reopenings', 1);
        $this->assertDatabaseCount('content_revisions', 2);
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->assertDatabaseCount('proposal_revisions', 0);
        $this->assertDatabaseHas('notification_outbox', ['event_id' => $first->json('data.id'), 'recipient_id' => $author->id, 'kind' => 'request.reopened']);
        foreach (['content_revisions', 'notification_outbox', 'api_idempotency'] as $table) {
            $storage = json_encode(DB::table($table)->get()->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString(self::REASON, $storage);
            $this->assertStringNotContainsString($proposal->code ?? 'Code privé absent', $storage);
        }
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolution')->assertNotFound();
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolutions')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $resolution->id)->assertJsonPath('data.0.proposal_version', 7)
            ->assertJsonPath('data.0.reopening.id', $first->json('data.id'))->assertHeader('Cache-Control', 'no-store, private');

        app(ResolveHelpRequestService::class)->resolve($owner, $parent->id, new ResolutionData(['proposal_id' => $other->id,
            'lock_version' => 5, 'proposal_lock_version' => 2, 'environment' => 'Contexte fictif après réouverture.',
            'validation_note' => 'Une nouvelle acceptation déclarée après réouverture.']), new IdempotencyKey((string) Str::uuid()));
        $this->browserRequest('POST', $this->path($parent), $payload, ['Idempotency-Key' => self::KEY])->assertStatus(409);
        $this->assertSame(6, $parent->refresh()->lock_version);
        $this->assertSame($proposalBefore, $proposal->refresh()->getAttributes());
        $history = $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolutions?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
        $this->assertSame(2, $history->json('meta.last_page'));
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolutions?per_page=1&page=2')->assertOk()
            ->assertJsonPath('data.0.request_lock_version', 6)->assertJsonPath('data.0.reopening.request_version_before', 4)
            ->assertJsonPath('data.0.reopening.request_version_after', 5);
        $this->assertDatabaseCount('request_reopenings', 1);
    }

    public function test_history_filters_invisible_former_contributor_before_pagination_and_total(): void
    {
        [$owner, , $parent, $former] = $this->fixture();
        $this->login($owner);
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertOk();
        $current = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => User::factory()->verified()]);
        $active = app(ResolveHelpRequestService::class)->resolve($owner, $parent->id, new ResolutionData([
            'proposal_id' => $current->id, 'lock_version' => 5, 'proposal_lock_version' => 1,
            'environment' => 'Nouveau contexte fictif.', 'validation_note' => 'Une nouvelle acceptation documentée après réouverture.',
        ]), new IdempotencyKey((string) Str::uuid()));
        $former->forceFill(['hidden_at' => now()])->save();
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolutions?per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('meta.last_page', 1)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)->assertJsonPath('data.0.reopening', null);
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolutions?per_page=1&page=2')->assertOk()->assertJsonCount(0, 'data');
    }

    #[DataProvider('incompatibleStates')]
    public function test_parent_state_is_rechecked_without_revoking_an_incompatible_resolution(string $state, int $status): void
    {
        [$owner, , $parent, , $resolution] = $this->fixture();
        $parent->forceFill(['state' => $state])->save();
        $this->login($owner);
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertStatus($status);
        $this->assertNoEffects();
        $this->assertNull($resolution->refresh()->revoked_at);
        $this->assertSame(4, $parent->refresh()->lock_version);
    }

    /** @return iterable<string, array{string, int}> */
    public static function incompatibleStates(): iterable
    {
        yield 'draft owner visible' => ['draft', 403];
        yield 'archived owner visible' => ['archived', 403];
        yield 'open new intention' => ['open', 409];
        yield 'in progress new intention' => ['in_progress', 409];
    }

    #[DataProvider('rawJson')]
    public function test_real_raw_json_float_and_malformed_objects_are_refused(string $content, string $field): void
    {
        [$owner, , $parent] = $this->fixture();
        $this->login($owner);
        Auth::forgetGuards();
        app()->forgetInstance('auth.driver');
        Auth::shouldUse('web');
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        $headers = ['Accept' => 'application/json', 'Origin' => 'https://app.haas.example.com', 'Idempotency-Key' => self::KEY,
            'X-XSRF-TOKEN' => $this->browserCookies['XSRF-TOKEN']];
        $this->call('POST', 'https://api.haas.example.com'.$this->path($parent), [], $this->browserCookies, [],
            array_merge($this->transformHeadersToServerVars($headers), ['CONTENT_TYPE' => 'application/json']), $content)
            ->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
        $this->assertNoEffects();
    }

    /** @return iterable<string, array{string, string}> */
    public static function rawJson(): iterable
    {
        yield 'integral floating JSON token' => ['{"lock_version":4.0,"reason":"Une raison suffisamment détaillée pour rouvrir."}', 'lock_version'];
        yield 'reason object' => ['{"lock_version":4,"reason":{"message":"Motif fictif sans secret."}}', 'reason'];
        yield 'nested reason object' => ['{"lock_version":4,"reason":{"outer":{"message":"Motif fictif sans secret."}}}', 'reason'];
        yield 'lock version object' => ['{"lock_version":{"value":4},"reason":"Une raison suffisamment détaillée pour rouvrir."}', 'lock_version'];
        yield 'empty object' => ['{}', 'body'];
        yield 'array root' => ['[]', 'body'];
        yield 'malformed JSON' => ['{"lock_version":4,', 'body'];
    }

    #[DataProvider('roles')]
    public function test_non_owner_has_no_resolution_power_including_admin(Role $role): void
    {
        [, , $parent, , $resolution] = $this->fixture();
        $this->login(User::factory()->verified()->create(['role' => $role]));
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertNull($resolution->refresh()->revoked_at);
        $this->assertNoEffects();
    }

    /** @return iterable<string, array{Role}> */
    public static function roles(): iterable
    {
        foreach (Role::cases() as $role) {
            yield $role->value => [$role];
        }
    }

    #[DataProvider('formerContributorChanges')]
    public function test_invisible_or_suspended_former_contributor_does_not_prevent_owner_reopening(string $kind): void
    {
        [$owner, $author, $parent, $proposal] = $this->fixture();
        match ($kind) {
            'hidden' => $proposal->forceFill(['hidden_at' => now()])->save(),
            'suspended' => $author->forceFill(['status' => 'suspended'])->save(),
            'unverified' => $author->forceFill(['email_verified_at' => null])->save(),
            default => throw new \InvalidArgumentException('Fixture B20 inconnue.'),
        };
        $this->login($owner);
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertOk();
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertOk();
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolutions')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
        $this->assertSame('accepted', $proposal->refresh()->state->value);
        $this->assertSame(7, $proposal->lock_version);
    }

    /** @return iterable<string, array{string}> */
    public static function formerContributorChanges(): iterable
    {
        foreach (['hidden', 'suspended', 'unverified'] as $kind) {
            yield $kind => [$kind];
        }
    }

    public function test_self_reopening_has_no_self_notification_and_keeps_legacy_unknown_snapshot(): void
    {
        [$owner, , $parent, $proposal, $resolution] = $this->fixture(self: true);
        $resolution->forceFill(['environment' => null, 'proposal_version' => null])->save();
        $this->login($owner);
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertOk();
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertSame('accepted', $proposal->refresh()->state->value);
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolutions')->assertOk()
            ->assertJsonPath('data.0.environment', null)->assertJsonPath('data.0.proposal_version', null)->assertJsonPath('data.0.self_resolution', true);
    }

    public function test_legacy_revoked_resolution_without_journal_has_no_invented_reopening(): void
    {
        [, , $parent, , $resolution] = $this->fixture();
        $resolution->forceFill(['revoked_at' => now(), 'environment' => null, 'proposal_version' => null, 'validation_note' => 'Note legacy courte.'])->save();
        $parent->forceFill(['state' => 'open'])->save();
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolutions')->assertOk()->assertJsonPath('data.0.reopening', null)
            ->assertJsonPath('data.0.environment', null)->assertJsonPath('data.0.proposal_version', null)->assertJsonPath('data.0.validation_note', 'Note legacy courte.');
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidInputs')]
    public function test_raw_invalid_or_server_input_is_rejected_without_effect(array $changes, string $field): void
    {
        [$owner, , $parent, , $resolution] = $this->fixture();
        $this->login($owner);
        $this->browserRequest('POST', $this->path($parent), array_replace(self::payload(), $changes), ['Idempotency-Key' => self::KEY])
            ->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
        $this->assertNoEffects();
        $this->assertNull($resolution->refresh()->revoked_at);
        $this->assertSame(4, $parent->refresh()->lock_version);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidInputs(): iterable
    {
        foreach (['4', 4.5, true, null, 0, [], 2147483647] as $index => $value) {
            yield 'version '.$index => [['lock_version' => $value], 'lock_version'];
        }
        foreach (['short' => str_repeat('a', 19), 'long Unicode' => str_repeat('é', 4001), 'invisible' => str_repeat("\u{200B}", 20),
            'link only' => '[Explication apparemment longue](https://example.test/only)', 'raw control' => "\x01".self::REASON,
            'secret' => 'api_key='.str_repeat('a', 40), 'array' => [], 'null' => null] as $name => $value) {
            yield 'reason '.$name => [['reason' => $value], 'reason'];
        }
        foreach (['reopened_by', 'resolution_id', 'request_id', 'reopened_at', 'request_version_after', 'state', 'unknown'] as $field) {
            yield 'server '.$field => [[$field => true], $field];
        }
    }

    public function test_unicode_limit_and_final_parent_increment_are_valid(): void
    {
        [$owner, , $parent] = $this->fixture();
        $parent->forceFill(['lock_version' => 2147483646])->save();
        $this->login($owner);
        $reason = str_repeat('é', 4000);
        $this->browserRequest('POST', $this->path($parent), ['lock_version' => 2147483646, 'reason' => "\u{200B}\u{00A0}".$reason."\u{00A0}"], ['Idempotency-Key' => self::KEY])
            ->assertOk()->assertJsonPath('data.reason', $reason)->assertJsonPath('data.request_version_after', 2147483647);
    }

    public function test_stale_version_and_hidden_or_changed_owner_replay_cannot_succeed(): void
    {
        [$owner, , $parent] = $this->fixture();
        $this->login($owner);
        $this->browserRequest('POST', $this->path($parent), ['lock_version' => 3, 'reason' => self::REASON], ['Idempotency-Key' => self::KEY])->assertStatus(409);
        $this->assertNoEffects();
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertOk();
        $parent->refresh()->forceFill(['lock_version' => 6])->save();
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertStatus(409);
        $parent->forceFill(['hidden_at' => now()])->save();
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertNotFound();
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolutions')->assertNotFound();
        $parent->forceFill(['hidden_at' => null, 'author_id' => User::factory()->verified()->create()->id])->save();
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertDatabaseCount('request_reopenings', 1);
        $this->assertDatabaseCount('content_revisions', 2);
    }

    public function test_suspension_and_unverified_owner_close_replay_without_duplicating_history(): void
    {
        [$owner, , $parent] = $this->fixture();
        $this->login($owner);
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertOk();
        $owner->forceFill(['email_verified_at' => null])->save();
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertForbidden();
        $owner->forceFill(['email_verified_at' => now(), 'status' => 'suspended'])->save();
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertDatabaseCount('request_reopenings', 1);
        $this->assertDatabaseCount('content_revisions', 2);
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->assertSame(5, $parent->refresh()->lock_version);
    }

    #[DataProvider('failureTargets')]
    public function test_forced_storage_failure_rolls_back_fact_revoke_parent_audits_and_outbox(string $target): void
    {
        [$owner, , $parent, $proposal, $resolution] = $this->fixture();
        $beforeParent = $parent->refresh()->getAttributes();
        $beforeProposal = $proposal->refresh()->getAttributes();
        $beforeResolution = $resolution->refresh()->getAttributes();
        $this->login($owner);
        DB::unprepared("CREATE FUNCTION fixture_b20_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Fixture B20 : stockage refusé.' USING ERRCODE = 'P0001'; END $$");
        $sql = match ($target) {
            'journal' => 'CREATE TRIGGER fixture_b20_failure BEFORE INSERT ON request_reopenings FOR EACH ROW EXECUTE FUNCTION fixture_b20_failure()',
            'audit' => "CREATE TRIGGER fixture_b20_failure BEFORE INSERT ON content_revisions FOR EACH ROW WHEN (NEW.action = 'resolution.revoked') EXECUTE FUNCTION fixture_b20_failure()",
            'outbox' => "CREATE TRIGGER fixture_b20_failure BEFORE INSERT ON notification_outbox FOR EACH ROW WHEN (NEW.kind = 'request.reopened') EXECUTE FUNCTION fixture_b20_failure()",
            'idempotency' => 'CREATE TRIGGER fixture_b20_failure BEFORE INSERT ON api_idempotency FOR EACH ROW EXECUTE FUNCTION fixture_b20_failure()',
            default => throw new \InvalidArgumentException('Fixture de stockage B20 inconnue.'),
        };
        DB::unprepared($sql);
        try {
            $response = $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertStatus(500);
            $this->assertStringNotContainsString(self::REASON, (string) $response->getContent());
            $this->assertSame($beforeParent, $parent->refresh()->getAttributes());
            $this->assertSame($beforeProposal, $proposal->refresh()->getAttributes());
            $this->assertSame($beforeResolution, $resolution->refresh()->getAttributes());
            $this->assertNoEffects();
        } finally {
            $table = match ($target) {
                'journal' => 'request_reopenings', 'audit' => 'content_revisions', 'outbox' => 'notification_outbox', default => 'api_idempotency'
            };
            DB::statement('DROP TRIGGER fixture_b20_failure ON '.$table);
            DB::statement('DROP FUNCTION fixture_b20_failure()');
        }
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertOk();
    }

    /** @return iterable<string, array{string}> */
    public static function failureTargets(): iterable
    {
        foreach (['journal', 'audit', 'outbox', 'idempotency'] as $target) {
            yield $target => [$target];
        }
    }

    public function test_http_session_csrf_key_and_query_requirements(): void
    {
        [$owner, , $parent] = $this->fixture();
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertUnauthorized();
        $this->login($owner);
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY], sendXsrf: false)->assertStatus(419);
        $this->browserRequest('POST', $this->path($parent), self::payload())->assertUnprocessable();
        $this->browserRequest('POST', $this->path($parent).'?reason=forged', self::payload(), ['Idempotency-Key' => self::KEY])->assertUnprocessable();
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolutions?per_page=51')->assertUnprocessable();
        $owner->forceFill(['email_verified_at' => null])->save();
        $this->browserRequest('POST', $this->path($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertNoEffects();
    }

    /** @return array{User, User, HelpRequest, Proposal, Resolution} */
    private function fixture(bool $self = false): array
    {
        $owner = User::factory()->verified()->create();
        $author = $self ? $owner : User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'resolved', 'lock_version' => 4]);
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $author->id, 'state' => 'accepted', 'lock_version' => 7, 'code' => '// code privé fictif B20']);
        $resolution = Resolution::factory()->create(['request_id' => $parent->id, 'proposal_id' => $proposal->id,
            'accepted_by' => $owner->id, 'proposal_version' => 7, 'environment' => 'Environnement fictif connu.', 'accepted_at' => now()->subMinute()]);

        return [$owner, $author, $parent, $proposal, $resolution];
    }

    /** @return array{lock_version: int, reason: string} */
    private static function payload(): array
    {
        return ['lock_version' => 4, 'reason' => self::REASON];
    }

    private function path(HelpRequest $parent): string
    {
        return '/api/v1/requests/'.$parent->id.'/reopen';
    }

    private function login(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }

    private function assertNoEffects(): void
    {
        foreach (['request_reopenings', 'content_revisions', 'notification_outbox', 'api_idempotency'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
