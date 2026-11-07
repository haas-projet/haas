<?php

declare(strict_types=1);

namespace App\Enums\Comparisons;

enum ComparisonSide: string
{
    case Baseline = 'baseline';
    case Candidate = 'candidate';
}
