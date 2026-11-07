<?php

declare(strict_types=1);

namespace App\Enums\Capsules;

enum CapsuleVisibility: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
}
