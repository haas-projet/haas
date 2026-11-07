<?php

declare(strict_types=1);

namespace App\Enums\Lab;

enum LabScenario: string
{
    case NominalEvent = 'nominal_event';
    case DuplicateEvent = 'duplicate_event';
    case DistinctEvents = 'distinct_events';
    case InvalidEvent = 'invalid_event';

    public function label(): string
    {
        return match ($this) {
            self::NominalEvent => 'Un événement valide',
            self::DuplicateEvent => 'Deux occurrences identiques',
            self::DistinctEvents => 'Deux événements distincts',
            self::InvalidEvent => 'Un événement invalide',
        };
    }
}
