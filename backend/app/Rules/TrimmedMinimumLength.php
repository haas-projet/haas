<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class TrimmedMinimumLength implements ValidationRule
{
    public function __construct(private int $minimum) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && mb_strlen(trim($value)) < $this->minimum) {
            $fail('Le texte doit contenir au moins '.$this->minimum.' caractères après retrait des espaces extérieurs.');
        }
    }
}
