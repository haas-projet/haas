<?php

declare(strict_types=1);

namespace App\Enums\VerificationCases;

// États des cas de vérification cités littéralement dans
// docs/product/CAHIER_DES_CHARGES.md:317. Draft est l'état initial après
// création ; submitted fige la révision courante ; reviewed/declined viennent
// d'une revue motivée (BV203) ; integrated vient d'une commande d'intégration
// qui lit un manifeste de release (BV203) ; withdrawn est atteint par
// l'auteur avec motif depuis draft ou submitted.
enum VerificationCaseState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Reviewed = 'reviewed';
    case Declined = 'declined';
    case Integrated = 'integrated';
    case Withdrawn = 'withdrawn';
}
