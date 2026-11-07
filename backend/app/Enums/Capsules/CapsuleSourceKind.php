<?php

declare(strict_types=1);

namespace App\Enums\Capsules;

enum CapsuleSourceKind: string
{
    case HelpRequest = 'help_request';
    case Editorial = 'editorial';
}
