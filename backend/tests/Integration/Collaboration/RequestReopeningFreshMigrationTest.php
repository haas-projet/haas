<?php

namespace Tests\Integration\Collaboration;

use App\Data\HelpRequests\ReopeningData;
use App\Data\Idempotency\IdempotencyKey;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\User;
use App\Services\HelpRequests\ReopenHelpRequestService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;
use Tests\Support\RequestReopeningFixtures;
use Tests\Support\TestDatabaseGuard;

final class RequestReopeningFreshMigrationTest extends PostgresTestCase
{
    use DatabaseMigrations, RequestReopeningFixtures;

    public function test_two_real_fresh_migrations_leave_reopening_binding_and_immutability_active(): void
    {
        TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
        foreach ([1, 2] as $iteration) {
            $code = Artisan::call('migrate:fresh', ['--force' => true]);
            $this->assertSame(0, $code, Artisan::output());
            $this->assertDatabaseCount('request_reopenings', 0);
            $this->assertSame(2, DB::table('pg_trigger')->whereIn('tgname', ['b20_request_reopening_guard', 'b20_reopened_resolution_guard'])->count());
        }
        $owner = User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'resolved', 'lock_version' => 4]);
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => User::factory()->verified(), 'state' => 'accepted', 'lock_version' => 7]);
        $resolution = Resolution::factory()->create(['request_id' => $parent->id, 'proposal_id' => $proposal->id, 'accepted_by' => $owner->id,
            'environment' => 'Contexte fictif.', 'proposal_version' => 7, 'accepted_at' => now()->subMinute()]);
        try {
            $fact = app(ReopenHelpRequestService::class)->reopen($owner, $parent->id, new ReopeningData([
                'lock_version' => 4, 'reason' => 'Une réouverture après deux installations PostgreSQL réelles.',
            ]), new IdempotencyKey('b2000000-5555-4444-8888-123456789abc'));
            $this->assertSame(5, $parent->refresh()->lock_version);
            $this->assertNotNull($resolution->refresh()->revoked_at);
            foreach (['fact', 'resolution'] as $kind) {
                $state = null;
                DB::beginTransaction();
                try {
                    if ($kind === 'fact') {
                        DB::table('request_reopenings')->where('id', $fact->id)->delete();
                    } else {
                        DB::table('resolutions')->where('id', $resolution->id)->update(['revoked_at' => null]);
                    }
                } catch (QueryException $error) {
                    $state = $error->errorInfo[0] ?? null;
                } finally {
                    DB::rollBack();
                }
                $this->assertSame('23514', $state);
            }
            $this->assertDatabaseHas('request_reopenings', ['id' => $fact->id, 'resolution_id' => $resolution->id]);
        } finally {
            $this->removeReopeningFixtures([$parent->id]);
        }
    }
}
