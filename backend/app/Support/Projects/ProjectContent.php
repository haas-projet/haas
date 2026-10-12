<?php

namespace App\Support\Projects;

use App\Enums\Projects\ProjectStage;
use App\Rules\InertHttpsUrl;
use App\Rules\NoLikelySecret;
use App\Rules\ProjectText;
use App\Rules\ProposalText;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Rule;

final class ProjectContent
{
    public const array FIELDS = ['name', 'summary', 'description', 'project_stage', 'help_sought', 'demo_url', 'repository_url'];

    /** @return array<string, array<mixed>> */
    public static function rules(bool $published = false): array
    {
        $text = ['string', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret];

        return [
            'name' => [$published ? 'required' : 'nullable', ...$text, new ProjectText(3, 80, false)],
            'summary' => [$published ? 'required' : 'nullable', ...$text, new ProjectText(30, 240, false)],
            'description' => [$published ? 'required' : 'nullable', ...$text, new ProjectText(80, 4000)],
            'project_stage' => ['sometimes', 'required', Rule::enum(ProjectStage::class)],
            'help_sought' => ['nullable', ...$text, new ProjectText(1, 1500)],
            'demo_url' => ['nullable', ...$text, 'max:2048', new InertHttpsUrl],
            'repository_url' => ['nullable', ...$text, 'max:2048', new InertHttpsUrl],
            'technology_ids' => [$published ? 'required' : 'sometimes', 'array', 'list', 'min:'.($published ? 1 : 0), 'max:5'],
            'technology_ids.*' => ['required', 'string', 'uuid', 'distinct:ignore_case'],
        ];
    }

    /** @param array<string, mixed> $data */
    public static function isValid(array $data): bool
    {
        return array_diff(array_keys($data), [...self::FIELDS, 'technology_ids']) === []
            && (! isset($data['project_stage']) || is_string($data['project_stage']))
            && (new Factory(new Translator(new ArrayLoader, 'fr')))->make($data, self::rules())->passes();
    }

    /** @param array<string, mixed> $data */
    public static function validate(array $data, bool $published): void
    {
        Validator::make($data, self::rules($published), self::messages())->validate();
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public static function normalize(array $attributes): array
    {
        foreach (['name', 'summary', 'description', 'help_sought'] as $field) {
            if (isset($attributes[$field]) && is_string($attributes[$field])) {
                $attributes[$field] = ProposalText::trim($attributes[$field]);
            }
        }

        return $attributes;
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return ['required' => 'Le champ :attribute est obligatoire.', 'string' => 'Le champ :attribute doit être du texte.',
            'array' => 'Fournissez une liste de technologies.', 'list' => 'Fournissez une liste ordonnée de technologies.',
            'max.array' => 'Sélectionnez au maximum cinq technologies.', 'min.array' => 'Sélectionnez au moins une technologie.',
            'max.string' => 'Le champ :attribute est limité à :max caractères.', 'uuid' => 'Sélectionnez un identifiant valide.',
            'distinct' => 'Une technologie ne peut apparaître qu’une fois.', 'enum' => 'Choisissez une phase déclarée autorisée.',
            'not_regex' => 'Le champ contient des caractères de contrôle non autorisés.'];
    }
}
