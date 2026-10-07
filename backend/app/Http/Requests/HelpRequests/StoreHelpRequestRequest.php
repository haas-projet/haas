<?php

namespace App\Http\Requests\HelpRequests;

use App\Data\HelpRequests\CreateHelpRequestData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\HelpRequests\HelpIntent;
use App\Enums\HelpRequests\SubmissionMode;
use App\Models\HelpRequest;
use App\Models\User;
use App\Support\HelpRequests\HelpRequestContent;
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

        return ['mode' => ['required', Rule::enum(SubmissionMode::class)], ...HelpRequestContent::rules($publish, $question)];
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
            HelpRequestContent::checkAttempts($validator, $this->input('attempts'), $this->input('mode') === 'publish');
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
        return HelpRequestContent::messages();
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey($this->header('Idempotency-Key', ''));
    }
}
