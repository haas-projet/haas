<?php

namespace App\Enums\Identity;

enum AccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
