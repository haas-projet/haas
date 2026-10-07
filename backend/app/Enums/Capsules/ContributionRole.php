<?php

declare(strict_types=1);

namespace App\Enums\Capsules;

enum ContributionRole: string
{
    case Diagnosis = 'diagnosis';
    case Fix = 'fix';
    case Documentation = 'documentation';
    case Test = 'test';
    case Case = 'case';
}
