<?php

declare(strict_types=1);

namespace App\Data\VerificationCases;

enum VerificationCaseParent: string
{
    case HelpRequest = 'help_request';
    case CapsuleVersion = 'capsule_version';
}
