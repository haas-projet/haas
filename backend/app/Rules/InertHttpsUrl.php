<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class InertHttpsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }
        $parts = parse_url($value);
        if (filter_var($value, FILTER_VALIDATE_URL) === false || ! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') === ''
            || isset($parts['user']) || isset($parts['pass'])
            || preg_match('/[\s\p{Z}\p{Cf}\p{Cc}]/u', $value) !== 0) {
            $fail('Utilisez une URL HTTPS valide, sans identifiants ni caractères de contrôle.');
        }
    }
}
