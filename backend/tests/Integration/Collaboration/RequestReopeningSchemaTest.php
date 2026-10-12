<?php

namespace Tests\Integration\Collaboration;

use App\Data\HelpRequests\ReopeningData;
use App\Data\Idempotency\IdempotencyKey;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\RequestReopening;
use App\Models\Resolution;
use App\Models\User;
use App\Services\HelpRequests\ReopenHelpRequestService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\PostgresTestCase;

final class RequestReopeningSchemaTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_upgrade_and_down_up_preserve_legacy_resolutions_with_unknown_snapshots(): void
    {
        [, $parent, , $resolution] = $this->fixture();
        $resolution->forceFill(['environment' => null, 'proposal_version' => null])->save();
        $before = $resolution->refresh()->getAttributes();
        $migration = require database_path('migrations/2026_10_12_000020_b20_request_reopenings.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('request_reopenings'));
        $this->assertSame($before, $resolution->refresh()->getAttributes());
        $migration->up();
        $this->assertTrue(Schema::hasTable('request_reopenings'));
        $this->assertSame($before, $resolution->refresh()->getAttributes());
        $this->assertSame('resolved', $parent->refresh()->state->value);
        $this->assertDatabaseCount('request_reopenings', 0);
    }

    public function test_down_refuses_known_reopening_before_any_schema_or_guard_is_removed(): void
    {
        [$owner, $parent] = $this->fixture();
        $fact = $this->apply($owner, $parent);
        $before = $fact->refresh()->getAttributes();
        $migration = require database_path('migrations/2026_10_12_000020_b20_request_reopenings.php');
        $refused = false;
        try {
            $migration->down();
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('historique', $error->getMessage());
            $refused = true;
        }
        $this->assertTrue($refused);
        $this->assertTrue(Schema::hasTable('request_reopenings'));
        $this->assertSame($before, $fact->refresh()->getAttributes());
        $this->sqlFailure(fn () => DB::table('request_reopenings')->where('id', $fact->id)->delete(), '23514');
        $this->assertSame(1, DB::table('pg_trigger')->where('tgname', 'b20_reopened_resolution_guard')->count());
    }

    #[DataProvider('immutableMutations')]
    public function test_fact_and_original_resolution_are_immutable_even_by_direct_sql(string $kind): void
    {
        [$owner, $parent, , $resolution] = $this->fixture();
        $fact = $this->apply($owner, $parent);
        $factBefore = $fact->refresh()->getAttributes();
        $resolutionBefore = $resolution->refresh()->getAttributes();
        $this->sqlFailure(fn () => match ($kind) {
            'reason' => DB::table('request_reopenings')->where('id', $fact->id)->update(['reason' => 'Une réécriture historique interdite après la réouverture.']),
            'delete fact' => DB::table('request_reopenings')->where('id', $fact->id)->delete(),
            'resolution note' => DB::table('resolutions')->where('id', $resolution->id)->update(['validation_note' => 'Une ancienne acceptation ne doit jamais être réécrite.']),
            'revoke date' => DB::table('resolutions')->where('id', $resolution->id)->update(['revoked_at' => null]),
            'resolution snapshot' => DB::table('resolutions')->where('id', $resolution->id)->update(['proposal_version' => 8]),
            'delete resolution' => DB::table('resolutions')->where('id', $resolution->id)->delete(),
            default => throw new \InvalidArgumentException,
        }, '23514');
        $this->assertSame($factBefore, $fact->refresh()->getAttributes());
        $this->assertSame($resolutionBefore, $resolution->refresh()->getAttributes());
    }

    /** @return iterable<string, array{string}> */
    public static function immutableMutations(): iterable
    {
        foreach (['reason', 'delete fact', 'resolution note', 'revoke date', 'resolution snapshot', 'delete resolution'] as $kind) {
            yield $kind => [$kind];
        }
    }

    public function test_binding_actor_before_after_and_unique_resolution_constraints_are_real(): void
    {
        [$owner, $parent, , $resolution] = $this->fixture();
        $fact = $this->apply($owner, $parent);
        $foreign = HelpRequest::factory()->for(User::factory()->verified(), 'author')->create(['state' => 'open', 'lock_version' => 5]);
        $attributes = $fact->getAttributes();
        $this->sqlFailure(fn () => DB::table('request_reopenings')->insert([...$attributes, 'id' => (string) Str::uuid(), 'request_id' => $foreign->id]), '23514');
        $this->sqlFailure(fn () => DB::table('request_reopenings')->insert([...$attributes, 'id' => (string) Str::uuid(), 'request_version_before' => 0]), '23514');
        $this->sqlFailure(fn () => DB::table('request_reopenings')->insert([...$attributes, 'id' => (string) Str::uuid(), 'request_version_before' => 2147483647, 'request_version_after' => 2147483647]), '23514');
        $this->sqlFailure(fn () => DB::table('request_reopenings')->insert([...$attributes, 'id' => (string) Str::uuid()]), '23505');
        $definition = DB::scalar("SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname = 'request_reopenings_resolution_binding'");
        $this->assertIsString($definition);
        $this->assertStringContainsString('FOREIGN KEY (request_id, resolution_id)', $definition);
        $this->assertStringContainsString('REFERENCES resolutions(request_id, id) ON DELETE RESTRICT', $definition);
        $this->assertNotNull($resolution->refresh()->revoked_at);
        $this->assertDatabaseCount('request_reopenings', 1);
    }

    private function sqlFailure(callable $operation, string $state): void
    {
        $failure = null;
        DB::beginTransaction();
        try {
            $operation();
        } catch (QueryException $error) {
            $failure = $error->errorInfo[0] ?? null;
        } finally {
            DB::rollBack();
        }
        $this->assertSame($state, $failure);
    }

    /** @return array{User, HelpRequest, Proposal, Resolution} */
    private function fixture(): array
    {
        $owner = User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'resolved', 'lock_version' => 4]);
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => User::factory()->verified(), 'state' => 'accepted', 'lock_version' => 7]);
        $resolution = Resolution::factory()->create(['request_id' => $parent->id, 'proposal_id' => $proposal->id, 'accepted_by' => $owner->id,
            'proposal_version' => 7, 'environment' => 'Contexte fictif.', 'accepted_at' => now()->subMinute()]);

        return [$owner, $parent, $proposal, $resolution];
    }

    private function apply(User $owner, HelpRequest $parent): RequestReopening
    {
        return app(ReopenHelpRequestService::class)->reopen($owner, $parent->id, new ReopeningData(['lock_version' => 4,
            'reason' => 'Une réouverture fictive motivée pour tester les contraintes.']), new IdempotencyKey('b2000000-4444-4444-8888-123456789abc'));
    }
}
