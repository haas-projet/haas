<?php

namespace App\Support\HelpRequests;

use App\Enums\HelpRequests\HelpIntent;
use App\Rules\NoLikelySecret;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ContentValidator;

final class HelpRequestContent
{
    public const FIELDS = ['title', 'goal', 'expected', 'observed', 'attempts', 'environment', 'code', 'code_language', 'primary_language', 'reproduction_url'];

    /** @return array<string, array<mixed>> */
    public static function rules(bool $publish, bool $question): array
    {
        $technical = $publish && ! $question;
        $text = ['string', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret];

        return [
            'help_intent' => ['required', Rule::enum(HelpIntent::class)],
            'title' => ['required', ...$text, 'min:'.($publish ? 15 : 1), 'max:140'],
            'goal' => [$publish ? 'required' : 'nullable', ...$text, 'min:'.($publish ? 30 : 1), 'max:2000'],
            'expected' => [$technical ? 'required' : 'nullable', ...$text, 'min:'.($publish ? 30 : 1), 'max:2000'],
            'observed' => [$publish ? 'required' : 'nullable', ...$text, 'min:'.($publish ? 30 : 1), 'max:4000'],
            'attempts' => [$technical ? 'required' : 'nullable', ...$text, 'max:3000'],
            'environment' => [$technical ? 'required' : 'nullable', ...$text, 'max:255'],
            'code' => ['nullable', ...$text, 'max:12000'],
            'code_language' => ['nullable', 'required_with:code', 'string', 'max:40', 'regex:/\A[a-z0-9][a-z0-9_+.#-]*\z/'],
            'primary_language' => ['sometimes', 'required', 'string', 'max:35', 'regex:/\A[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})*\z/'],
            'reproduction_url' => ['nullable', ...$text, 'max:2048', 'url:https', 'not_regex:~https://[^/]*@~i'],
            'technologies' => [$publish ? 'required' : 'sometimes', 'array', 'list', 'min:'.($publish ? 1 : 0), 'max:5'],
            'technologies.*' => ['array:id,version_label'],
            'technologies.*.id' => ['required', 'string', 'uuid', 'distinct:ignore_case', 'exists:technologies,id'],
            'technologies.*.version_label' => ['nullable', ...$text, 'max:40'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'required' => 'Le champ :attribute est obligatoire.',
            'required_with' => 'Le champ :attribute est obligatoire lorsque :values est renseigné.',
            'string' => 'Le champ :attribute doit être du texte.',
            'min.string' => 'Le champ :attribute doit contenir au moins :min caractères.',
            'max.string' => 'Le champ :attribute est limité à :max caractères.',
            'array' => 'Le champ :attribute doit respecter la liste des valeurs autorisées.',
            'list' => 'Le champ :attribute doit être une liste.',
            'min.array' => 'Sélectionnez au moins :min technologie.',
            'max.array' => 'Sélectionnez au maximum :max technologies.',
            'enum' => 'Choisissez une valeur autorisée pour :attribute.',
            'uuid' => 'Sélectionnez un identifiant de technologie valide.',
            'exists' => 'Une technologie sélectionnée est indisponible. Rechargez la liste.',
            'distinct' => 'Une technologie ne peut être sélectionnée qu’une fois.',
            'url' => 'Utilisez un lien HTTPS valide ; son contenu ne sera pas récupéré.',
            'regex' => 'Le format du champ :attribute est invalide.',
            'not_regex' => 'Le champ :attribute contient des caractères ou informations non autorisés.',
        ];
    }

    /** @return array<mixed> */
    public static function noteRules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'min:20', 'max:1000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret];
    }

    public static function validateNote(?string $note, bool $required): void
    {
        Validator::make(['edit_note' => $note], ['edit_note' => self::noteRules($required)], self::messages())->validate();
    }

    public static function checkAttempts(ContentValidator $validator, mixed $attempts, bool $publish): void
    {
        if ($publish && is_string($attempts) && $attempts !== '' && mb_strtolower($attempts) !== 'aucune' && mb_strlen($attempts) < 20) {
            $validator->errors()->add('attempts', 'Décrivez les tentatives en 20 caractères minimum ou indiquez « aucune ».');
        }
    }

    /** @param array<string, mixed> $content */
    public static function validate(array $content, bool $publish): void
    {
        $validator = Validator::make($content, self::rules($publish, ($content['help_intent'] ?? null) === HelpIntent::AskQuestion->value), self::messages());
        $validator->after(fn (ContentValidator $validator) => self::checkAttempts($validator, $content['attempts'] ?? null, $publish));
        $validator->validate();
    }
}
