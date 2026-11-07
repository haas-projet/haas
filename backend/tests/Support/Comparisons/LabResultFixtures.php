<?php

declare(strict_types=1);

namespace Tests\Support\Comparisons;

use App\Enums\Comparisons\ComparisonSide;
use App\Enums\Lab\LabRunState;
use App\Models\Comparisons\ComparisonRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BV208 — Fixtures de laboratoire pour les tests Integration. STRICTEMENT
 * test_only_fixture : jamais déplacé sous backend/app/. Toute assertion
 * injectée est étiquetée « démonstration pédagogique » dans son champ
 * `expected`. Les transitions respectent les triggers B33 (voir
 * backend/database/migrations/2026_10_11_230000_b33_lab_control_registry.php:95-196)
 * et les CHECKs : queued -> running -> passed/failed, avec insertion de
 * lab_result sur l'enfant en running avant la transition terminale.
 *
 * Le worker réel (B36/B37) n'est pas livré. Ces fixtures ne décrivent pas
 * une exécution ; elles simulent la paire d'évidences nécessaire pour que
 * ReconcileComparisonsService puisse exercer sa logique.
 */
trait LabResultFixtures
{
    /**
     * @param  array<string, bool>  $assertions  Carte identifiant -> réussite (clé non vide, valeur strictement booléenne).
     */
    private function recordTerminalResult(
        ComparisonRun $comparison,
        ComparisonSide $side,
        LabRunState $terminal,
        array $assertions,
    ): string {
        if (! in_array($terminal, [LabRunState::Passed, LabRunState::Failed], true)) {
            throw new \InvalidArgumentException('Seuls passed et failed produisent un lab_result terminal.');
        }
        // b33_guard_result: state=passed exige 0 échec, state=failed exige >=1.
        $hasFailure = in_array(false, $assertions, true);
        if ($terminal === LabRunState::Passed && $hasFailure) {
            throw new \InvalidArgumentException('État passed incompatible avec une assertion en échec.');
        }
        if ($terminal === LabRunState::Failed && ! $hasFailure) {
            throw new \InvalidArgumentException('État failed exige au moins une assertion en échec.');
        }
        $runId = $this->childIdFor($comparison, $side);
        // started_at et finished_at >= created_at (CHECK lab_runs_dates_consistent).
        // On lit le created_at de la ligne, puis on utilise now() UTC comme horodatage
        // commun pour running puis terminal (égalité acceptée par `finished_at >= started_at`).
        /** @var object{created_at: string}|null $existing */
        $existing = DB::connection('pgsql')->selectOne('SELECT created_at FROM lab_runs WHERE id = ?', [$runId]);
        if ($existing === null) {
            throw new \RuntimeException('Enfant lab_run introuvable.');
        }
        $createdAt = CarbonImmutable::parse((string) $existing->created_at)->utc();
        $instant = CarbonImmutable::now('UTC');
        if ($instant->lessThan($createdAt)) {
            $instant = $createdAt;
        }
        // 1) queued -> running (B33: transition autorisée, started_at impératif).
        DB::connection('pgsql')->table('lab_runs')
            ->where('id', $runId)
            ->update([
                'state' => LabRunState::Running->value,
                'started_at' => $instant->toDateTimeString(),
            ]);
        // 2) INSERT lab_result (complete oblige lab_run.state = 'running' et
        //    created_at(result) >= created_at(run), voir b33_guard_result).
        DB::connection('pgsql')->table('lab_results')->insert([
            'id' => (string) Str::uuid(),
            'run_id' => $runId,
            'state' => $terminal->value,
            'complete' => true,
            'assertions' => json_encode(
                $this->synthesizeAssertions($assertions),
                JSON_THROW_ON_ERROR,
            ),
            'runtime' => json_encode([
                'kind' => 'test_only_fixture',
                'label' => 'démonstration pédagogique',
                'duration_ms' => 10,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $instant->toDateTimeString(),
        ]);
        // 3) running -> passed/failed (B33 réclame le lab_result cohérent).
        DB::connection('pgsql')->table('lab_runs')
            ->where('id', $runId)
            ->update([
                'state' => $terminal->value,
                'finished_at' => $instant->toDateTimeString(),
                'duration_ms' => 10,
            ]);

        return $runId;
    }

    private function markChildError(ComparisonRun $comparison, ComparisonSide $side): string
    {
        return $this->markChildTerminalWithoutResult($comparison, $side, LabRunState::Error);
    }

    private function markChildTimedOut(ComparisonRun $comparison, ComparisonSide $side): string
    {
        return $this->markChildTerminalWithoutResult($comparison, $side, LabRunState::TimedOut);
    }

    private function markChildTerminalWithoutResult(
        ComparisonRun $comparison,
        ComparisonSide $side,
        LabRunState $terminal,
    ): string {
        $runId = $this->childIdFor($comparison, $side);
        /** @var object{created_at: string}|null $existing */
        $existing = DB::connection('pgsql')->selectOne('SELECT created_at FROM lab_runs WHERE id = ?', [$runId]);
        if ($existing === null) {
            throw new \RuntimeException('Enfant lab_run introuvable.');
        }
        $createdAt = CarbonImmutable::parse((string) $existing->created_at)->utc();
        $instant = CarbonImmutable::now('UTC');
        if ($instant->lessThan($createdAt)) {
            $instant = $createdAt;
        }
        DB::connection('pgsql')->table('lab_runs')
            ->where('id', $runId)
            ->update([
                'state' => $terminal->value,
                'finished_at' => $instant->toDateTimeString(),
            ]);

        return $runId;
    }

    /** Simule une comparaison créée hors du budget en reculant son created_at. */
    private function ageComparisonBeyondBudget(ComparisonRun $comparison, int $seconds = 120): void
    {
        $past = CarbonImmutable::now('UTC')->subSeconds($seconds);
        DB::connection('pgsql')->table('comparison_runs')
            ->where('id', $comparison->id)
            ->update(['created_at' => $past->toDateTimeString()]);
    }

    private function childIdFor(ComparisonRun $comparison, ComparisonSide $side): string
    {
        /** @var object{id:string}|null $child */
        $child = DB::connection('pgsql')->table('lab_runs')
            ->where('comparison_id', $comparison->id)
            ->where('comparison_side', $side->value)
            ->first(['id']);
        if ($child === null) {
            throw new \RuntimeException('Enfant lab_run introuvable pour '.$side->value.'.');
        }

        return (string) $child->id;
    }

    /**
     * @param  array<string, bool>  $results
     * @return list<array{key:string, passed:bool, expected:string, observed:string}>
     */
    private function synthesizeAssertions(array $results): array
    {
        $out = [];
        foreach ($results as $key => $passed) {
            if ($key === '') {
                throw new \InvalidArgumentException('Carte d\'assertions synthétique invalide (clé vide).');
            }
            $out[] = [
                'key' => $key,
                'passed' => $passed,
                'expected' => 'démonstration pédagogique',
                'observed' => $passed ? 'match' : 'mismatch',
            ];
        }

        return $out;
    }
}
