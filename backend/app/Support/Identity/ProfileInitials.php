<?php

namespace App\Support\Identity;

use Illuminate\Support\Str;

final class ProfileInitials
{
    public static function fromHandle(string $handle): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $handle, -1, PREG_SPLIT_NO_EMPTY);
        if (! $words) {
            return '?';
        }
        $initials = mb_substr($words[0], 0, 1);
        if (count($words) > 1) {
            $initials .= mb_substr($words[count($words) - 1], 0, 1);
        }

        return mb_substr(Str::upper($initials), 0, 2);
    }
}
