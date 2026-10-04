<?php

namespace App\Http\Requests\HelpRequests;

use App\Data\HelpRequests\CreateHelpRequestData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\HelpRequests\HelpIntent;
use App\Enums\HelpRequests\SubmissionMode;
use App\Models\HelpRequest;
use App\Models\User;
use App\Rules\NoLikelySecret;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreHelpRequestRequest extends FormRequest
{
    public function member(): User
    {
        $user = $this->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    public function authorize(): bool
    {
        return $this->member()->can('create', HelpRequest::class);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $publish = $this->input('mode') === SubmissionMode::Publish->value;
        $question = $this->input('help_intent') === HelpIntent::AskQuestion->value;
        $technical = $publish && ! $question;
        $text = ['string', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret];

        return [
            'mode' => ['required', Rule::enum(SubmissionMode::class)],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! IdempotencyKey::valid($this->header('Idempotency-Key', ''))) {
                $validator->errors()->add('Idempotency-Key', 'Utilisez un UUID v4 par intention et conservez-le pour les relances.');
            }
            foreach (array_diff(array_keys($this->all()), ['mode', 'help_intent', 'title', 'goal', 'expected', 'observed', 'attempts', 'environment', 'code', 'code_language', 'primary_language', 'reproduction_url', 'technologies']) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être fourni.');
            }
            $attempts = $this->input('attempts');
            if ($this->input('mode') === 'publish' && is_string($attempts) && $attempts !== '' && mb_strtolower($attempts) !== 'aucune' && mb_strlen($attempts) < 20) {
                $validator->errors()->add('attempts', 'Décrivez les tentatives en 20 caractères minimum ou indiquez « aucune ».');
            }
        });
    }

    public function requestData(): CreateHelpRequestData
    {
        $data = $this->validated();

        return new CreateHelpRequestData(SubmissionMode::from($data['mode']), HelpIntent::from($data['help_intent']), [
            'title' => $data['title'], 'goal' => $data['goal'] ?? null, 'expected' => $data['expected'] ?? null,
            'observed' => $data['observed'] ?? null, 'attempts' => $data['attempts'] ?? null, 'environment' => $data['environment'] ?? null,
            'code' => $data['code'] ?? null, 'code_language' => $data['code_language'] ?? null,
            'primary_language' => $data['primary_language'] ?? 'fr', 'reproduction_url' => $data['reproduction_url'] ?? null,
        ], array_values(array_map($this->technologyData(...), $data['technologies'] ?? [])));
    }

    /**
     * @param  array{id: string, version_label?: ?string}  $technology
     * @return array{id: string, version_label: ?string}
     */
    private function technologyData(array $technology): array
    {
        return ['id' => strtolower($technology['id']), 'version_label' => $technology['version_label'] ?? null];
    }

    /** @return array<string, string> */
    public function messages(): array
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

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey($this->header('Idempotency-Key', ''));
    }
}
