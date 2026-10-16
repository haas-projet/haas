<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Comparisons\ReconcileComparisonsService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * BV208 — Commande interne de réconciliation d'une comparaison B1. Elle
 * n'est appelée par rien en production : aucune planification n'est ajoutée
 * à routes/console.php, aucun worker n'est livré (B36/B37 restent à faire).
 * Elle existe pour alimenter les tests Integration (fixtures de lab_results)
 * et pour permettre à l'opérateur, après GO humain, d'exercer manuellement
 * le chemin après la livraison d'un vrai worker. Modèle d'écriture :
 * lab:bind-definition (BindLabDefinition).
 */
final class ReconcileComparisons extends Command
{
    protected $signature = 'comparisons:reconcile {id : UUID de la comparaison à réconcilier}';

    protected $description = 'Réconcilier une comparaison B1 (verrou avisé, finalisation UNIQUE). Jamais planifié ; aucun worker livré.';

    public function handle(ReconcileComparisonsService $service): int
    {
        $id = (string) $this->argument('id');
        if (! Str::isUuid($id)) {
            $this->error('Réconciliation refusée : UUID attendu.');

            return self::FAILURE;
        }
        try {
            $report = $service->reconcile($id);
        } catch (QueryException) {
            $this->error('Réconciliation refusée : stockage applicatif indisponible.');

            return self::FAILURE;
        }
        if ($report->status === 'not_found') {
            $this->error('Comparaison inconnue.');

            return self::FAILURE;
        }
        $stateLabel = $report->state === null ? '-' : $report->state->value;
        $exposedLabel = $report->exposedOutcome === null ? '-' : $report->exposedOutcome->value;
        $this->line(sprintf(
            '%s : status=%s state=%s exposed=%s reason=%s',
            $report->comparisonId,
            $report->status,
            $stateLabel,
            $exposedLabel,
            $report->reason,
        ));

        return self::SUCCESS;
    }
}
