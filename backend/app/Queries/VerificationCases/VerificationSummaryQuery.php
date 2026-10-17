<?php

declare(strict_types=1);

namespace App\Queries\VerificationCases;

use App\Data\VerificationCases\VerificationSummaryData;
use App\Data\VerificationCases\VerificationSummaryLabEvidence;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Comparisons\ComparisonOutcome;
use App\Enums\Comparisons\ComparisonState;
use App\Models\Capsules\CapsuleContributor;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Capsules\ReuseReport;
use App\Services\Comparisons\ComparisonOutcomeCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * BV209 — Fiche de vérification versionnée. Lit UNIQUEMENT les blocs d'une
 * version publiée appartenant à une capsule visible ; toute autre id
 * (brouillon, en revue, retirée, capsule masquée, UUID inconnu) renvoie 404
 * via NotFoundHttpException (CAHIER_DES_CHARGES.md:1665 et AC45).
 *
 * Trois SELECTs seulement (hors chargement des parents) :
 * 1. CapsuleContributor::with('user') — handle public + rôle, aucun courriel
 *    ni identifiant interne.
 * 2. ReuseReport::with('user') — déclarations humaines (toutes marquées
 *    `is_internal` par le serveur en B28 pour les owners/contributeurs).
 * 3. Jointure lab_runs + lab_results + comparison_runs sur version_id. En
 *    l'absence de lab_results réels, le bloc rend « unavailable ».
 *
 * Pas d'eager loading dépendant du nombre de contributeurs/déclarations :
 * la Query produit le même nombre de requêtes pour 5 ou 30 lignes.
 */
final class VerificationSummaryQuery
{
    public function __construct(private readonly ComparisonOutcomeCalculator $calculator) {}

    public function get(string $versionId): VerificationSummaryData
    {
        $version = CapsuleVersion::query()
            ->with(['capsule'])
            ->whereKey($versionId)
            ->first();
        if ($version === null) {
            throw new NotFoundHttpException;
        }
        $capsule = $version->capsule;
        if ($capsule === null) {
            throw new NotFoundHttpException;
        }
        // AC45 : rien dans la fiche pour une version non publiée (brouillon,
        // en revue, changements demandés, retirée) ni pour une capsule masquée.
        if ($version->state !== CapsuleVersionState::Published
            || $capsule->visibility !== CapsuleVisibility::Visible
        ) {
            throw new NotFoundHttpException;
        }

        $contributors = $this->contributorsFor($versionId);
        $casesExamined = $this->casesExaminedFor($versionId);
        $observationsDeclared = $this->observationsDeclaredFor($versionId);
        $labEvidence = $this->labEvidenceFor($versionId);

        return new VerificationSummaryData(
            versionId: $version->id,
            capsuleSlug: (string) $capsule->getAttribute('slug'),
            versionLabel: $version->version_label,
            publishedAtIso: $version->published_at?->toIso8601String() ?? '',
            limits: $version->limits === null ? null : (string) $version->limits,
            contributors: $contributors,
            casesExamined: $casesExamined,
            observationsDeclared: $observationsDeclared,
            labEvidence: $labEvidence,
        );
    }

    /** @return list<array{handle:string,role:string}> */
    private function contributorsFor(string $versionId): array
    {
        /** @var Collection<int, CapsuleContributor> $rows */
        $rows = CapsuleContributor::query()
            ->with('user:id,handle')
            ->where('version_id', $versionId)
            ->orderBy('created_at')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $user = $row->getRelation('user');
            $handle = $user === null ? null : (string) $user->getAttribute('handle');
            if ($handle === null || $handle === '') {
                continue;
            }
            $out[] = [
                'handle' => $handle,
                'role' => $row->contribution_role->value,
            ];
        }

        return $out;
    }

    /** @return list<array{scenario_key:string,suite_digest:string,release_commit:string,integrated_at:string,reviewer_handle:string,source:string}> */
    private function casesExaminedFor(string $versionId): array
    {
        // Les cas intégrés sont ceux dont la révision est liée à un scénario
        // (case_scenario_links) ET dont le cas pointe sur cette version.
        // Jointure explicite pour un seul SELECT quelle que soit la volumétrie.
        $rows = DB::connection('pgsql')->select(<<<'SQL'
            SELECT
                csl.scenario_key AS scenario_key,
                csl.suite_digest AS suite_digest,
                csl.release_commit AS release_commit,
                csl.integrated_at AS integrated_at,
                reviewer.handle AS reviewer_handle
            FROM case_scenario_links csl
            INNER JOIN verification_case_revisions vcr ON vcr.id = csl.case_revision_id
            INNER JOIN verification_cases vc ON vc.id = vcr.case_id
            INNER JOIN users reviewer ON reviewer.id = csl.reviewer_id
            WHERE vc.capsule_version_id = ? AND vc.state = 'integrated'
            ORDER BY csl.integrated_at ASC
        SQL, [$versionId]);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'scenario_key' => (string) $row->scenario_key,
                'suite_digest' => (string) $row->suite_digest,
                'release_commit' => (string) $row->release_commit,
                'integrated_at' => CarbonImmutable::parse((string) $row->integrated_at)->utc()->toIso8601String(),
                'reviewer_handle' => (string) $row->reviewer_handle,
                'source' => 'ci',
            ];
        }

        return $out;
    }

    /** @return list<array{handle:string,outcome:string,observation:string,environment:string,reported_at:string,is_internal:bool,source:string}> */
    private function observationsDeclaredFor(string $versionId): array
    {
        /** @var Collection<int, ReuseReport> $rows */
        $rows = ReuseReport::query()
            ->with('user:id,handle')
            ->where('version_id', $versionId)
            ->orderBy('reported_at')
            ->orderBy('created_at')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $user = $row->getRelation('user');
            $handle = $user === null ? null : (string) $user->getAttribute('handle');
            if ($handle === null || $handle === '') {
                continue;
            }
            $out[] = [
                'handle' => $handle,
                'outcome' => $row->outcome->value,
                'observation' => (string) $row->getAttribute('observation'),
                'environment' => (string) $row->getAttribute('environment'),
                'reported_at' => CarbonImmutable::parse((string) $row->getAttribute('reported_at'))->toDateString(),
                'is_internal' => $row->is_internal,
                'source' => 'human_declaration',
            ];
        }

        return $out;
    }

    private function labEvidenceFor(string $versionId): VerificationSummaryLabEvidence
    {
        // Un bloc « disponible » n'est produit que lorsqu'au moins un
        // lab_result existe pour cette version (CAHIER_DES_CHARGES.md:1649).
        $runs = DB::connection('pgsql')->select(<<<'SQL'
            SELECT lr.id AS id, lr.scenario AS scenario_key, lr.state AS state,
                   lr.finished_at AS finished_at, lr.comparison_id AS comparison_id
            FROM lab_runs lr
            INNER JOIN lab_results res ON res.run_id = lr.id
            WHERE lr.version_id = ?
            ORDER BY lr.finished_at ASC, lr.created_at ASC
        SQL, [$versionId]);
        if ($runs === []) {
            return VerificationSummaryLabEvidence::unavailable();
        }
        $runList = [];
        $comparisonIds = [];
        foreach ($runs as $run) {
            $runList[] = [
                'id' => (string) $run->id,
                'scenario_key' => (string) $run->scenario_key,
                'state' => (string) $run->state,
                'finished_at' => $run->finished_at === null
                    ? null
                    : CarbonImmutable::parse((string) $run->finished_at)->utc()->toIso8601String(),
                'source' => 'lab',
            ];
            if ($run->comparison_id !== null) {
                $comparisonIds[(string) $run->comparison_id] = true;
            }
        }

        $comparisons = [];
        if ($comparisonIds !== []) {
            $compRows = DB::connection('pgsql')->table('comparison_runs')
                ->whereIn('id', array_keys($comparisonIds))
                ->orderBy('finished_at')
                ->get(['id', 'state', 'outcome', 'finished_at']);
            foreach ($compRows as $row) {
                $state = ComparisonState::from((string) $row->state);
                $exposed = $this->calculator->expose(
                    $state,
                    $row->outcome === null ? null : ComparisonOutcome::from((string) $row->outcome),
                );
                $comparisons[] = [
                    'id' => (string) $row->id,
                    'state' => $state->value,
                    'outcome' => $exposed?->value,
                    'finished_at' => $row->finished_at === null
                        ? null
                        : CarbonImmutable::parse((string) $row->finished_at)->utc()->toIso8601String(),
                    'source' => 'lab',
                ];
            }
        }

        return VerificationSummaryLabEvidence::withEvidence($runList, $comparisons);
    }
}
