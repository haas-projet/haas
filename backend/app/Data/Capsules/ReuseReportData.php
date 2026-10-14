<?php

declare(strict_types=1);

namespace App\Data\Capsules;

use App\Enums\Capsules\ReuseReportOutcome;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Charge d'un retour humain : environnement libre, date de retour,
 * `outcome` strict, observation 20-2000 caractères non blancs.
 * CAHIER_DES_CHARGES.md:500-502.
 */
final readonly class ReuseReportData
{
    public function __construct(
        public string $environment,
        public DateTimeImmutable $reportedAt,
        public ReuseReportOutcome $outcome,
        public string $observation,
    ) {
        $environmentTrimmed = trim($environment);
        if ($environmentTrimmed === '' || strlen($environment) > 200) {
            throw new InvalidArgumentException('environment hors bornes.');
        }
        $observationTrimmed = trim($observation);
        if (strlen($observationTrimmed) < 20 || strlen($observation) > 2000) {
            throw new InvalidArgumentException('observation hors bornes.');
        }
    }
}
