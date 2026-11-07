<?php

namespace App\Support\HelpRequests;

use App\Rules\NoLikelySecret;
use App\Rules\ProposalText;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

final class ReopeningContent
{
    public const array FIELDS = ['lock_version', 'reason'];

    /** @return array<string, array<mixed>> */
    public static function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer:strict', 'min:1', 'max:2147483646'],
            'reason' => ['required', 'string', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret, new ProposalText],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return ['lock_version.*' => 'Indiquez la version entière actuelle de la demande.',
            'reason.*' => 'Expliquez la réouverture en 20 à 4 000 caractères utiles, sans secret ni caractère de contrôle.'];
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
