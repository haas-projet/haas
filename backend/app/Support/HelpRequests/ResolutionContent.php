<?php

namespace App\Support\HelpRequests;

use App\Rules\NoLikelySecret;
use App\Rules\ProposalText;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

final class ResolutionContent
{
    public const array FIELDS = ['proposal_id', 'lock_version', 'proposal_lock_version', 'environment', 'validation_note'];

    /** @return array<string, array<mixed>> */
    public static function rules(): array
    {
        $text = ['required', 'string', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret];

        return [
            'proposal_id' => ['required', 'string', 'uuid'],
            'lock_version' => ['required', 'integer:strict', 'min:1', 'max:2147483646'],
            // Accepting freezes the content; only the parent version is incremented.
            'proposal_lock_version' => ['required', 'integer:strict', 'min:1', 'max:2147483647'],
            'environment' => [...$text, static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && (! mb_check_encoding($value, 'UTF-8') || mb_strlen(ProposalText::trim($value), 'UTF-8') < 1 || mb_strlen(ProposalText::trim($value), 'UTF-8') > 255)) {
                    $fail('Précisez le contexte déclaré en 1 à 255 caractères.');
                }
            }],
            'validation_note' => [...$text, new ProposalText],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'proposal_id.*' => 'Sélectionnez une proposition de cette demande.',
            'lock_version.*' => 'Indiquez la version entière actuelle de la demande.',
            'proposal_lock_version.*' => 'Indiquez la version entière de la proposition consultée.',
            'environment.*' => 'Précisez le contexte déclaré en 1 à 255 caractères, sans secret ni caractère de contrôle.',
            'validation_note.*' => 'Expliquez ce qui vous a aidé en 20 à 4 000 caractères utiles, sans secret ni caractère de contrôle.',
        ];
    }

    /** @param array<string, mixed> $data */
    public static function isValid(array $data): bool
    {
        return array_diff(array_keys($data), self::FIELDS) === []
            && (new Factory(new Translator(new ArrayLoader, 'fr')))->make($data, self::rules())->passes();
    }

    /** @param array<string, mixed> $data */
    public static function validate(array $data): void
    {
        Validator::make($data, self::rules(), self::messages())->validate();
    }
}
