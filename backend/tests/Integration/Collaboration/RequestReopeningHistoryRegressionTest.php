<?php

namespace Tests\Integration\Collaboration;

use App\Data\HelpRequests\ReopeningData;
use App\Data\Idempotency\IdempotencyKey;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\User;
use App\Queries\HelpRequests\VisibleRequestReopeningsQuery;
use App\Services\HelpRequests\ReopenHelpRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class RequestReopeningHistoryRegressionTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    #[DataProvider('incoherentAttributions')]
    public function test_incoherent_historical_attribution_is_filtered_before_total_and_page(string $case): void
    {
        [$owner, $parent, $proposal, $resolution] = $this->fixture();
        $other = User::factory()->verified()->create();
        $journal = null;
        if ($case === 'owner changed after reopening' || $case === 'known version changed after reopening') {
            $journal = app(ReopenHelpRequestService::class)->reopen($owner, $parent->id, new ReopeningData([
                'lock_version' => 4, 'reason' => 'Un motif fictif documenté pour vérifier son attribution historique.',
            ]), new IdempotencyKey('b2000000-6666-4444-8888-123456789abc'));
        }
        match ($case) {
            'different acceptor' => $resolution->forceFill(['accepted_by' => $other->id])->save(),
            'owner changed', 'owner changed after reopening' => $parent->forceFill(['author_id' => $other->id])->save(),
            'known version mismatch', 'known version changed after reopening' => $proposal->forceFill(['lock_version' => 8])->save(),
            default => throw new \InvalidArgumentException('Fixture de cohérence B20 inconnue.'),
        };
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolutions?per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('resolutions', 1);
        if ($journal !== null) {
            $this->assertDatabaseHas('request_reopenings', ['id' => $journal->id, 'reopened_by' => $owner->id]);
            $this->assertSame(0, app(VisibleRequestReopeningsQuery::class)->forActor(null)->count());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function incoherentAttributions(): iterable
    {
        foreach (['different acceptor', 'owner changed', 'known version mismatch', 'owner changed after reopening', 'known version changed after reopening'] as $case) {
            yield $case => [$case];
        }
    }

    public function test_coherent_revoked_legacy_keeps_null_snapshots_and_its_reopening_after_later_parent_versions(): void
    {
        [$owner, $parent, , $resolution] = $this->fixture();
        $resolution->forceFill(['environment' => null, 'proposal_version' => null])->save();
        $fact = app(ReopenHelpRequestService::class)->reopen($owner, $parent->id, new ReopeningData([
            'lock_version' => 4, 'reason' => 'Un ancien contexte inconnu reste inconnu après réouverture.',
        ]), new IdempotencyKey('b2000000-6666-4444-8888-123456789abd'));
        $parent->refresh()->forceFill(['lock_version' => 6])->save();
        $this->browserRequest('GET', '/api/v1/requests/'.$parent->id.'/resolutions?per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.environment', null)->assertJsonPath('data.0.proposal_version', null)
            ->assertJsonPath('data.0.reopening.id', $fact->id)->assertJsonPath('data.0.reopening.request_version_after', 5)
            ->assertJsonPath('data.0.request_lock_version', 6);
        $this->assertNull(DB::table('resolutions')->where('id', $resolution->id)->value('proposal_version'));
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
}
