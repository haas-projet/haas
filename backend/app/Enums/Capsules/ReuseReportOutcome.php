<?php

declare(strict_types=1);

namespace App\Enums\Capsules;

/**
 * B28 — Résultat déclaratif d'un retour de réutilisation (CAHIER_DES_CHARGES.md:500).
 * Jamais interprété comme un résultat machine ; la terminologie reprend
 * littéralement « Reproduit », « Partiellement reproduit » et « Non reproduit ».
 */
enum ReuseReportOutcome: string
{
    case Reproduced = 'reproduced';
    case Partial = 'partial';
    case Failed = 'failed';
}
