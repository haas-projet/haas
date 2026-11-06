<?php

declare(strict_types=1);

namespace App\Enums\VerificationCases;

enum ReviewDecision: string
{
    case Reviewed = 'reviewed';
    case Declined = 'declined';
}
