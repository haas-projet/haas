<?php

declare(strict_types=1);

namespace Tests\Support\Comparisons;

use App\Enums\Comparisons\ComparisonSide;
use App\Enums\Comparisons\ComparisonState;
use App\Enums\Lab\LabRunState;
use App\Infrastructure\Lab\ApprovedLabRegistry;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Comparisons\ComparisonRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fixtures BV205 — jamais des preuves d'exécution ; destinées à alimenter les
 * tests de lecture et de concurrence sans passer par l'orchestration réelle
 * (B36 non livré).
 */
trait ComparisonRunFixtures
{
    use ComparisonFixtures;

    /** @param array<string, mixed> $overrides */
    private function pendingComparisonRun(string $profileId, User $actor, array $overrides = []): ComparisonRun
    {
        $id = (string) Str::uuid();
        DB::connection('pgsql')->table('comparison_runs')->insert(array_replace([
            'id' => $id,
            'profile_id' => $profileId,
            'actor_id' => $actor->id,
            'request_key' => (string) Str::uuid(),
            'state' => ComparisonState::Queued->value,
            'outcome' => null,
            'input_digest' => hash('sha256', 'BV205 fixture '.$id),
            'seed' => (string) Str::uuid(),
            'env_snapshot' => json_encode(['release' => 'fixture'], JSON_THROW_ON_ERROR),
            'created_at' => CarbonImmutable::now('UTC'),
        ], $overrides));

        return ComparisonRun::query()->findOrFail($id);
    }

    /**
     * Insère un enfant lab_run attaché à une comparaison, en respectant la
     * snapshot définitionnelle de B33 (trigger b33_guard_run).
     */
    private function queuedComparisonChild(ComparisonRun $parent, string $definitionId, ComparisonSide $side, CapsuleVersion $version): string
    {
        $manifest = (new ApprovedLabRegistry)->manifest();
        $id = (string) Str::uuid();
        DB::connection('pgsql')->table('lab_runs')->insert([
            'id' => $id,
            'definition_id' => $definitionId,
            'version_id' => $version->id,
            'user_id' => $parent->actor_id,
            'request_key' => (string) Str::uuid(),
            'scenario' => 'nominal_event',
            'release' => $manifest['release'],
            'module_version' => $manifest['module_version'],
            'source_commit' => $manifest['source_commit'],
            'code_digest' => $manifest['code_digest'],
            'suite_digest' => $manifest['suite_digest'],
            'version_digest' => (string) $version->content_digest,
            'state' => LabRunState::Queued->value,
            'created_at' => CarbonImmutable::now('UTC'),
            'comparison_id' => $parent->id,
            'comparison_side' => $side->value,
        ]);

        return $id;
    }
}
