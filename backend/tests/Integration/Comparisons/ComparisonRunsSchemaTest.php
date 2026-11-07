<?php

declare(strict_types=1);

namespace Tests\Integration\Comparisons;

use App\Infrastructure\Lab\ApprovedLabRegistry;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\Comparisons\ComparisonFixtures;
use Tests\Support\Lab\RegistryFixtures;

/**
 * Les CHECK et contraintes SQL sont vérifiés par insertion directe ; aucune
 * route publique n'écrit comparison_runs ni comparison_side.
 */
final class ComparisonRunsSchemaTest extends PostgresTestCase
{
    use ComparisonFixtures, RefreshDatabase, RegistryFixtures;

    public function test_state_outside_enum_is_rejected(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        try {
            DB::connection('pgsql')->table('comparison_runs')->insert($this->row($profileId, $actor->id, ['state' => 'exploded']));
            $this->fail('L\'état « exploded » doit être refusé par le CHECK state_values.');
        } catch (QueryException $e) {
            // L'ordre d'évaluation des CHECK n'est pas garanti : state_values
            // ou outcome_requires_terminal (via outcome IS NULL OR state =
            // completed) peuvent se déclencher. Les deux prouvent le refus.
            $this->assertMatchesRegularExpression('/comparison_runs_state_values|comparison_runs_outcome_requires_terminal/', $e->getMessage());
        }
    }

    public function test_outcome_outside_enum_is_rejected(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('comparison_runs_outcome_values');
        DB::connection('pgsql')->table('comparison_runs')->insert($this->row($profileId, $actor->id, ['state' => 'completed', 'outcome' => 'fantasy', 'finished_at' => now()->utc()]));
    }

    public function test_outcome_requires_completed(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('comparison_runs_outcome_requires_terminal');
        DB::connection('pgsql')->table('comparison_runs')->insert($this->row($profileId, $actor->id, ['state' => 'queued', 'outcome' => 'improved']));
    }

    public function test_input_digest_must_be_lowercase_hex_64(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('comparison_runs_input_digest_shape');
        DB::connection('pgsql')->table('comparison_runs')->insert($this->row($profileId, $actor->id, ['input_digest' => 'XX'.str_repeat('a', 62)]));
    }

    public function test_env_snapshot_must_be_a_json_object(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('comparison_runs_env_snapshot_is_object');
        DB::connection('pgsql')->table('comparison_runs')->insert($this->row($profileId, $actor->id, ['env_snapshot' => json_encode(['x'])]));
    }

    public function test_unique_actor_request_key(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        $sharedKey = (string) Str::uuid();
        DB::connection('pgsql')->table('comparison_runs')->insert($this->row($profileId, $actor->id, ['request_key' => $sharedKey]));
        $this->expectException(QueryException::class);
        DB::connection('pgsql')->table('comparison_runs')->insert($this->row($profileId, $actor->id, ['request_key' => $sharedKey]));
    }

    public function test_dates_running_requires_started_at(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('comparison_runs_dates_consistent');
        DB::connection('pgsql')->table('comparison_runs')->insert($this->row($profileId, $actor->id, ['state' => 'running']));
    }

    public function test_lab_runs_comparison_side_values(): void
    {
        [$profileId, $actor, $version, $definitionId] = $this->seedProfileActorAndDefinition();
        $comparisonId = $this->insertComparisonRun($profileId, $actor->id);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('lab_runs_comparison_side_values');
        DB::connection('pgsql')->table('lab_runs')->insert($this->childRow($definitionId, $version, $actor->id, [
            'comparison_id' => $comparisonId,
            'comparison_side' => 'bystander',
        ]));
    }

    public function test_lab_runs_comparison_pair_consistent_requires_both_or_none(): void
    {
        [$profileId, $actor, $version, $definitionId] = $this->seedProfileActorAndDefinition();
        $comparisonId = $this->insertComparisonRun($profileId, $actor->id);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('lab_runs_comparison_pair_consistent');
        DB::connection('pgsql')->table('lab_runs')->insert($this->childRow($definitionId, $version, $actor->id, [
            'comparison_id' => $comparisonId,
            'comparison_side' => null,
        ]));
    }

    public function test_lab_runs_comparison_side_unique_per_comparison(): void
    {
        [$profileId, $actor, $version, $definitionId] = $this->seedProfileActorAndDefinition();
        $comparisonId = $this->insertComparisonRun($profileId, $actor->id);
        DB::connection('pgsql')->table('lab_runs')->insert($this->childRow($definitionId, $version, $actor->id, [
            'comparison_id' => $comparisonId,
            'comparison_side' => 'baseline',
        ]));
        $this->expectException(QueryException::class);
        DB::connection('pgsql')->table('lab_runs')->insert($this->childRow($definitionId, $version, $actor->id, [
            'comparison_id' => $comparisonId,
            'comparison_side' => 'baseline',
        ]));
    }

    public function test_dropping_a_referenced_profile_is_restricted(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        $this->insertComparisonRun($profileId, $actor->id);
        $this->expectException(QueryException::class);
        DB::connection('pgsql')->table('comparison_profiles')->where('id', $profileId)->delete();
    }

    public function test_dropping_a_referenced_comparison_is_restricted(): void
    {
        [$profileId, $actor, $version, $definitionId] = $this->seedProfileActorAndDefinition();
        $comparisonId = $this->insertComparisonRun($profileId, $actor->id);
        DB::connection('pgsql')->table('lab_runs')->insert($this->childRow($definitionId, $version, $actor->id, [
            'comparison_id' => $comparisonId,
            'comparison_side' => 'baseline',
        ]));
        $this->expectException(QueryException::class);
        DB::connection('pgsql')->table('comparison_runs')->where('id', $comparisonId)->delete();
    }

    /** @return array{0:string, 1:User} */
    private function seedProfileAndActor(): array
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $actor = User::factory()->verified()->create();

        return [$profileId, $actor];
    }

    /** @return array{0:string, 1:User, 2:CapsuleVersion, 3:string} */
    private function seedProfileActorAndDefinition(): array
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $actor = User::factory()->verified()->create();
        $definitionId = $this->definition($version);

        return [$profileId, $actor, $version, $definitionId];
    }

    private function insertComparisonRun(string $profileId, string $actorId, string $requestKey = ''): string
    {
        $row = $this->row($profileId, $actorId, ['request_key' => $requestKey !== '' ? $requestKey : (string) Str::uuid()]);
        DB::connection('pgsql')->table('comparison_runs')->insert($row);

        return (string) $row['id'];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(string $profileId, string $actorId, array $overrides = []): array
    {
        return array_replace([
            'id' => (string) Str::uuid(),
            'profile_id' => $profileId,
            'actor_id' => $actorId,
            'request_key' => (string) Str::uuid(),
            'state' => 'queued',
            'outcome' => null,
            'input_digest' => str_repeat('a', 64),
            'seed' => (string) Str::uuid(),
            'env_snapshot' => json_encode(['release' => 'fixture'], JSON_THROW_ON_ERROR),
            'started_at' => null,
            'finished_at' => null,
            'created_at' => now()->utc(),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function childRow(string $definitionId, CapsuleVersion $version, string $userId, array $overrides = []): array
    {
        $manifest = (new ApprovedLabRegistry)->manifest();

        return array_replace([
            'id' => (string) Str::uuid(),
            'definition_id' => $definitionId,
            'version_id' => $version->id,
            'user_id' => $userId,
            'request_key' => (string) Str::uuid(),
            'scenario' => 'nominal_event',
            'release' => $manifest['release'],
            'module_version' => $manifest['module_version'],
            'source_commit' => $manifest['source_commit'],
            'code_digest' => $manifest['code_digest'],
            'suite_digest' => $manifest['suite_digest'],
            'version_digest' => (string) $version->content_digest,
            'state' => 'queued',
            'created_at' => now()->utc(),
        ], $overrides);
    }
}
