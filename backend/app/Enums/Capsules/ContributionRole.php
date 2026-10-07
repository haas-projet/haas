<?php

declare(strict_types=1);

namespace App\Enums\Capsules;

enum ContributionRole: string
{
    case Author = 'author';
    case Reviewer = 'reviewer';
    case Contributor = 'contributor';
    case Maintainer = 'maintainer';
}
