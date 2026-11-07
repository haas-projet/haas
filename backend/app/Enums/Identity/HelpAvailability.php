<?php

namespace App\Enums\Identity;

// Disponibilité volontaire, jamais déduite d'une session ou d'une présence en ligne.
enum HelpAvailability: string
{
    case NotSpecified = 'not_specified';
    case Available = 'available';
    case Unavailable = 'unavailable';
}
