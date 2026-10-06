<?php

namespace App\Rules;

use App\Support\Identity\GithubProfileLink;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class GithubUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! GithubProfileLink::allowed($value)) {
            $fail('Utilisez un lien https://github.com/ sans identifiants, port, paramètres ni fragment.');
        }
    }
}
