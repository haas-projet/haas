<?php

declare(strict_types=1);

namespace App\Enums\Capsules;

enum ArtifactDistributionStatus: string
{
    case Inactive = 'inactive';
    case Approved = 'approved';
}
