<?php

declare(strict_types=1);

namespace App\Enums\HelpRequests;

// Valeurs citées dans docs/architecture/ARCHITECTURE.md:255.
// Les transitions sont décidées par les Services B14–B21 (ARCHITECTURE.md:300-324),
// elles ne sont pas inscrites dans l'enum à ce stade.
enum HelpRequestState: string
{
    case Draft = 'draft';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Archived = 'archived';
}
