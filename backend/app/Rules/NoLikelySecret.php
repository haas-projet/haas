<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NoLikelySecret implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }
        if (preg_match('/PRIVATE KEY|\bgh[pousr]_[A-Za-z0-9]{20,}|\bsk-[A-Za-z0-9_-]{20,}/i', $value)
            || preg_match('/\b(?:api[_-]?key|api[_-]?token|access[_-]?token|client[_-]?secret|password|secret)\s*[:=]\s*["\x27]?[^\s"\x27]{8,}/i', $value)) {
            $fail('Retirez la valeur qui ressemble à un secret avant l’enregistrement. Ce contrôle est indicatif.');
        }
    }
}
