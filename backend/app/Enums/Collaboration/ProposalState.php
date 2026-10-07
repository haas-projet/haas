<?php

declare(strict_types=1);

namespace App\Enums\Collaboration;

// Valeurs citées dans docs/architecture/ARCHITECTURE.md:256.
// Les transitions sont décidées par les Services B18–B21,
// elles ne sont pas inscrites dans l'enum à ce stade.
enum ProposalState: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case NotSelected = 'not_selected';
}
