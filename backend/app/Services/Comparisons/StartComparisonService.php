<?php

declare(strict_types=1);

namespace App\Services\Comparisons;

use App\Data\Comparisons\ComparisonRunData;
use App\Data\Comparisons\StartComparisonData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Enums\Comparisons\ComparisonSide;
use App\Enums\Comparisons\ComparisonState;
use App\Enums\Lab\LabRunState;
use App\Infrastructure\Comparisons\ComparisonProfileRegistry;
use App\Infrastructure\Lab\LabCapacityGuard;
use App\Models\User;
use App\Queries\Comparisons\ApprovedProfileQuery;
use App\Services\Idempotency\IdempotencyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * BV206 — Démarre une comparaison B1 en réservant atomiquement un emplacement
 * et deux unités partagées avec le laboratoire simple. CAHIER §44 ligne 1651 :
 * « un seul run réellement actif au maximum globalement ; les deux enfants
 * sont séquentiels ; aucune seconde voie ne contourne les places et le quota ».
 *
 * Décision du propriétaire du domaine (consignée, question ouverte) :
 * - L'unité de capacité est un EMPLACEMENT. Un run isolé = 1 emplacement.
 *   Une comparaison = 1 emplacement partagé par ses deux enfants (comptage
 *   COUNT(DISTINCT COALESCE(comparison_id, id)) dans LabCapacityGuard). Sans
 *   cela, l'insertion du second enfant serait refusée sous « queued compte
 *   comme actif ».
 * - Quota horaire : 2 unités consommées par comparaison (deux lignes
 *   lab_runs enfants = 2 unités), sans changer UNITS_PER_SIMPLE_RUN.
 *
 * B34 n'exécute rien : aucun worker, les runs restent queued (B36, hors
 * périmètre). L'outcome machine ne devient jamais écrit par cette route :
 * BV208 ouvrira la révélation contrôlée.
 */
final class StartComparisonService
{
    public const ROUTE_TARGET = 'POST /api/v1/comparisons';

    /** Nombre d'unités consommées par une comparaison : deux enfants, deux lab_runs. */
    public const UNITS_PER_COMPARISON = 2;

    public function __construct(
        private readonly ApprovedProfileQuery $profiles,
        private readonly ComparisonProfileRegistry $registry,
        private readonly LabCapacityGuard $capacity,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function handle(User $actor, StartComparisonData $data, string $idempotencyKey): ComparisonRunData
    {
        $this->assertLabEnabled();
        $key = new IdempotencyKey($idempotencyKey);
        $idempotency = new IdempotencyData(self::ROUTE_TARGET, $key, $data->payload());

        /** @var ComparisonRunData $queued */
        $queued = $this->idempotency->execute(
            actor: $actor,
            data: $idempotency,
            authorize: static function (): void {
                // Les middlewares auth:sanctum, verified et EnsureAccountIsActive
                // posent la porte « member » ; la visibilité du profil et de la
                // version sont contrôlées sous verrou dans operation().
            },
            operation: function (User $current) use ($data): StoredCommandResult {
                return $this->reserveAndQueue($current, $data);
            },
            resolve: function (User $current, StoredCommandResult $stored): ComparisonRunData {
                $comparisonId = (string) ($stored->references['comparison'] ?? '');
                $baselineId = (string) ($stored->references['baseline'] ?? '');
                $candidateId = (string) ($stored->references['candidate'] ?? '');
                $run = DB::connection('pgsql')->table('comparison_runs')
                    ->where('id', $comparisonId)
                    ->where('actor_id', $current->id)
                    ->first();
                if ($run === null) {
                    throw new \RuntimeException('Comparaison introuvable au rejeu.');
                }

                return new ComparisonRunData(
                    id: $comparisonId,
                    profileId: (string) $run->profile_id,
                    state: ComparisonState::from((string) $run->state),
                    createdAt: CarbonImmutable::parse((string) $run->created_at)->utc()->toIso8601ZuluString(),
                    startedAt: $run->started_at === null ? null : CarbonImmutable::parse((string) $run->started_at)->utc()->toIso8601ZuluString(),
                    finishedAt: $run->finished_at === null ? null : CarbonImmutable::parse((string) $run->finished_at)->utc()->toIso8601ZuluString(),
                    followUrl: '/api/v1/comparisons/'.$comparisonId,
                    followUrls: array_values(array_filter([
                        $baselineId === '' ? null : '/api/v1/lab-runs/'.$baselineId,
                        $candidateId === '' ? null : '/api/v1/lab-runs/'.$candidateId,
                    ])),
                );
            },
        );

        return $queued;
    }

    private function reserveAndQueue(User $actor, StartComparisonData $data): StoredCommandResult
    {
        // Verrou avisé partagé avec le lanceur simple : runs isolés et
        // comparaisons prennent le même emplacement global (CAHIER §44
        // ligne 1651).
        $this->capacity->acquireAdvisoryLock();
        $this->capacity->assertRoomForUnits($actor, self::UNITS_PER_COMPARISON);

        $resolved = $this->profiles->get($data->comparisonProfileId);
        $profile = $resolved['profile'];
        $version = $resolved['version'];
        $definition = $resolved['definition'];

        $now = CarbonImmutable::now('UTC');
        $comparisonId = (string) Str::uuid();
        $baselineRunId = (string) Str::uuid();
        $candidateRunId = (string) Str::uuid();
        $baselineRequestKey = (string) Str::uuid();
        $candidateRequestKey = (string) Str::uuid();

        // Empreinte canonique commune aux deux côtés (CAHIER §44 ligne 1647 :
        // « même oracle d'attendu, les mêmes identifiants d'assertion et la
        // même configuration d'exécution »). Les namespaces de fixtures
        // restent distincts via comparison_side ; le digest reflète la paire.
        $inputDigest = hash('sha256', json_encode([
            'profile_id' => $profile->id,
            'scenario' => $data->scenario->value,
            'release' => $definition->release,
            'module_version' => $definition->moduleVersion,
            'source_commit' => $this->registry->expectedCandidateSourceCommit(),
            'suite_digest' => $definition->suiteDigest,
            'input_schema_version' => $profile->input_schema_version,
        ], JSON_THROW_ON_ERROR));

        DB::connection('pgsql')->table('comparison_runs')->insert([
            'id' => $comparisonId,
            'profile_id' => $profile->id,
            'actor_id' => $actor->id,
            'request_key' => (string) Str::uuid(),
            'state' => ComparisonState::Queued->value,
            'outcome' => null,
            'input_digest' => $inputDigest,
            'seed' => (string) Str::uuid(),
            'env_snapshot' => json_encode([
                'release' => $definition->release,
                'module_version' => $definition->moduleVersion,
            ], JSON_THROW_ON_ERROR),
            'started_at' => null,
            'finished_at' => null,
            'created_at' => $now,
        ]);

        foreach ([ComparisonSide::Baseline, ComparisonSide::Candidate] as $side) {
            [$runId, $requestKey] = $side === ComparisonSide::Baseline
                ? [$baselineRunId, $baselineRequestKey]
                : [$candidateRunId, $candidateRequestKey];
            DB::connection('pgsql')->table('lab_runs')->insert([
                'id' => $runId,
                'definition_id' => $definition->id,
                'version_id' => $version->id,
                'user_id' => $actor->id,
                'request_key' => $requestKey,
                'scenario' => $data->scenario->value,
                'release' => $definition->release,
                'module_version' => $definition->moduleVersion,
                'source_commit' => $this->registry->expectedCandidateSourceCommit(),
                'code_digest' => $definition->codeDigest,
                'suite_digest' => $definition->suiteDigest,
                'version_digest' => $definition->versionDigest,
                'state' => LabRunState::Queued->value,
                'created_at' => $now,
                'comparison_id' => $comparisonId,
                'comparison_side' => $side->value,
            ]);
        }

        return new StoredCommandResult(
            status: 202,
            references: [
                'comparison' => $comparisonId,
                'baseline' => $baselineRunId,
                'candidate' => $candidateRunId,
            ],
        );
    }

    private function assertLabEnabled(): void
    {
        if (config('lab.enabled') !== true) {
            throw new ServiceUnavailableHttpException(30, 'Laboratoire indisponible.');
        }
    }
}
