<?php

declare(strict_types=1);

namespace App\Enums\Comparisons;

enum ComparisonOutcome: string
{
    case Improved = 'improved';
    case Unchanged = 'unchanged';
    case Regressed = 'regressed';
    case Mixed = 'mixed';
    case Inconclusive = 'inconclusive';
}
