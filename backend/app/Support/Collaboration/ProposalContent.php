<?php

namespace App\Support\Collaboration;

use App\Rules\NoLikelySecret;
use App\Rules\ProposalText;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

final class ProposalContent
{
    public const array FIELDS = ['diagnosis', 'fix', 'verification', 'limits', 'code'];

    /** @return array<string, array<mixed>> */
    public static function rules(bool $partial = false): array
    {
        $rules = [];
        foreach (array_slice(self::FIELDS, 0, 4) as $field) {
            $rules[$field] = [...($partial ? ['sometimes'] : []), 'required', 'string',
                'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new ProposalText, new NoLikelySecret];
        }
        $rules['code'] = ['sometimes', 'nullable', 'string', 'max:12000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                    $fail('Le code doit utiliser un encodage UTF-8 valide.');
                }
            }, new NoLikelySecret];

        return $rules;
    }

    /** @param array<string, mixed> $content */
    public static function isValid(array $content, bool $partial = false): bool
    {
        if ($content === [] || array_diff(array_keys($content), self::FIELDS) !== []) {
            return false;
        }

        // The DTO uses the same rules without an application, identity or database.
        return (new Factory(new Translator(new ArrayLoader, 'fr')))->make($content, self::rules($partial))->passes();
    }

    /** @param array<string, mixed> $content */
    public static function validate(array $content): void
    {
        Validator::make($content, self::rules())->validate();
    }
}
