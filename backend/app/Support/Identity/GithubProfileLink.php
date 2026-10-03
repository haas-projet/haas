<?php

namespace App\Support\Identity;

final class GithubProfileLink
{
    public static function allowed(mixed $value): bool
    {
        $parts = is_string($value) ? parse_url($value) : false;

        return is_string($value) && mb_strlen($value) <= 2048 && filter_var($value, FILTER_VALIDATE_URL) !== false
            && is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && strtolower($parts['host'] ?? '') === 'github.com'
            && array_intersect(['user', 'pass', 'port', 'query', 'fragment'], array_keys($parts)) === []
            && ! preg_match('/[\\\\\s\x00-\x1f\x7f]/u', $value);
    }
}
