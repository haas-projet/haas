<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ProjectText implements ValidationRule
{
    public function __construct(private int $minimum, private int $maximum, private bool $multiline = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }
        if (! mb_check_encoding($value, 'UTF-8')) {
            $fail('Utilisez un texte UTF-8 valide.');

            return;
        }
        $text = ProposalText::trim($value);
        $useful = preg_replace('/\p{Cf}/u', '', $text) ?? $text;
        if (mb_strlen($useful, 'UTF-8') < $this->minimum || mb_strlen($text, 'UTF-8') > $this->maximum
            || (! $this->multiline && preg_match('/[\r\n]/', $text) === 1)) {
            $fail('Renseignez entre '.$this->minimum.' et '.$this->maximum.' caractères utiles.');
        }
    }
}
