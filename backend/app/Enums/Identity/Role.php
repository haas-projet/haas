<?php

namespace App\Enums\Identity;

enum Role: string
{
    case Member = 'member';
    case Moderator = 'moderator';
    case Admin = 'admin';
}
