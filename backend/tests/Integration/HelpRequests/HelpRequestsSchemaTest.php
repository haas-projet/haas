<?php

namespace Tests\Integration\HelpRequests;

use App\Enums\Collaboration\ProposalState;
use App\Enums\HelpRequests\HelpRequestState;
use App\Models\Comment;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\Technology;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class HelpRequestsSchemaTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_valid_help_request_graph_persists_with_factory_values(): void
    {
        // Aucun défaut en base sur `state` ; la factory fixe draft/proposed
        // pour que le test reflète le flux attendu via Services (B14+).
        $author = User::factory()->create();
        $technology = Technology::factory()->create();

        $request = HelpRequest::factory()->create(['author_id' => $author->id])->refresh();
        $this->assertTrue(Str::isUuid($request->id));
        $this->assertSame(HelpRequestState::Draft, $request->state);
        $this->assertSame(1, $request->lock_version);

        $request->technologies()->attach($technology, ['version_label' => '11.x']);
        $this->assertSame('11.x', DB::table('request_technologies')->where('request_id', $request->id)->where('technology_id', $technology->id)->value('version_label'));

        $proposal = Proposal::factory()->create(['request_id' => $request->id, 'author_id' => $author->id])->refresh();
        $this->assertSame(ProposalState::Proposed, $proposal->state);

        Comment::factory()->create(['request_id' => $request->id, 'author_id' => $author->id]);
        $request->refresh();
        $this->assertCount(1, $request->comments);

        $resolution = Resolution::factory()->create([
            'request_id' => $request->id,
            'proposal_id' => $proposal->id,
            'accepted_by' => $author->id,
        ])->refresh();
        $this->assertSame($proposal->id, $resolution->proposal_id);
    }

    public function test_pivot_rejects_duplicate_technology_on_same_request(): void
    {
        $request = HelpRequest::factory()->create();
        $technology = Technology::factory()->create();
        $request->technologies()->attach($technology);
        $this->assertSqlFailure(fn () => $request->technologies()->attach($technology), '23505');
    }

    public function test_resolution_rejects_cross_request_proposal(): void
    {
        // Contrainte d'appartenance proposition/demande demandée par
        // docs/execution/tasks.json:147 et docs/execution/PLAN_COMMITS.md:133.
        // Posée via FK composite (request_id, proposal_id) → proposals (request_id, id).
        $owner = User::factory()->create();
        $firstRequest = HelpRequest::factory()->create(['author_id' => $owner->id]);
        $otherRequest = HelpRequest::factory()->create();
        $foreignProposal = Proposal::factory()->create(['request_id' => $otherRequest->id]);

        $this->assertSqlFailure(
            fn () => DB::table('resolutions')->insert([
                'id' => (string) Str::uuid(),
                'request_id' => $firstRequest->id,
                'proposal_id' => $foreignProposal->id,
                'accepted_by' => $owner->id,
                'validation_note' => 'Tentative croisée.',
                'accepted_at' => CarbonImmutable::now(),
                'created_at' => CarbonImmutable::now(),
                'updated_at' => CarbonImmutable::now(),
            ]),
            '23503'
        );
    }

    public function test_only_one_active_resolution_per_request(): void
    {
        // docs/architecture/ARCHITECTURE.md:316-320
        $request = HelpRequest::factory()->create();
        $firstProposal = Proposal::factory()->create(['request_id' => $request->id]);
        $secondProposal = Proposal::factory()->create(['request_id' => $request->id]);
        Resolution::factory()->create([
            'request_id' => $request->id,
            'proposal_id' => $firstProposal->id,
        ]);

        $this->assertSqlFailure(
            fn () => Resolution::factory()->create([
                'request_id' => $request->id,
                'proposal_id' => $secondProposal->id,
            ]),
            '23505'
        );
    }

    public function test_revoked_resolution_frees_the_slot_for_a_new_active_one(): void
    {
        $request = HelpRequest::factory()->create();
        $firstProposal = Proposal::factory()->create(['request_id' => $request->id]);
        $firstResolution = Resolution::factory()->create([
            'request_id' => $request->id,
            'proposal_id' => $firstProposal->id,
        ]);
        $firstResolution->forceFill(['revoked_at' => CarbonImmutable::now()])->save();

        $secondProposal = Proposal::factory()->create(['request_id' => $request->id]);
        $secondResolution = Resolution::factory()->create([
            'request_id' => $request->id,
            'proposal_id' => $secondProposal->id,
        ]);
        $this->assertNull($secondResolution->revoked_at);
    }

    public function test_check_constraints_reject_invalid_state_and_short_body(): void
    {
        $request = HelpRequest::factory()->create();
        $this->assertSqlFailure(
            fn () => DB::table('help_requests')->where('id', $request->id)->update(['state' => 'invalid_state']),
            '23514'
        );

        $this->assertSqlFailure(
            fn () => Comment::factory()->create(['request_id' => $request->id, 'body' => '']),
            '23514'
        );

        $this->assertSqlFailure(
            fn () => DB::table('proposals')->where('id', Proposal::factory()->create(['request_id' => $request->id])->id)->update(['state' => 'invalid_state']),
            '23514'
        );
    }

    public function test_state_columns_are_not_null_without_database_default(): void
    {
        // Aucun défaut en base : une INSERT raw qui omet `state` échoue en 23502.
        $this->assertSqlFailure(
            fn () => DB::table('help_requests')->insert([
                'id' => (string) Str::uuid(),
                'author_id' => User::factory()->create()->id,
                'title' => str_pad('Blocage test ', 20, 'x'),
                'goal' => str_pad('Objectif attendu minimum ', 40, 'x'),
                'expected' => str_pad('Résultat attendu minimum ', 40, 'x'),
                'observed' => str_pad('Comportement observé minimum ', 40, 'x'),
                'attempts' => str_pad('Tentatives faites ', 30, 'x'),
                'environment' => 'Linux',
                'lock_version' => 1,
                'created_at' => CarbonImmutable::now(),
                'updated_at' => CarbonImmutable::now(),
            ]),
            '23502'
        );
    }

    public function test_cascade_removes_request_technologies_when_help_request_is_deleted(): void
    {
        // request_technologies.request_id est en CASCADE par analogie B05
        // (user_technologies.user_id cascadeOnDelete dans la migration B05).
        $request = HelpRequest::factory()->create();
        $technology = Technology::factory()->create();
        $request->technologies()->attach($technology);
        $this->assertSame(1, DB::table('request_technologies')->where('request_id', $request->id)->count());

        DB::table('help_requests')->where('id', $request->id)->delete();
        $this->assertSame(0, DB::table('request_technologies')->where('request_id', $request->id)->count());
    }

    /** @param Closure(): mixed $operation */
    private function assertSqlFailure(Closure $operation, string $sqlState): void
    {
        try {
            DB::transaction($operation);
            $this->fail('La contrainte PostgreSQL devait refuser cette écriture.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->getCode());
        }
    }
}
