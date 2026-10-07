<?php

namespace App\Http\Requests\HelpRequests;

use App\Data\HelpRequests\UpdateHelpRequestData;
use App\Enums\HelpRequests\HelpIntent;
use App\Support\HelpRequests\HelpRequestContent;
use Illuminate\Validation\Validator;

final class UpdateHelpRequestRequest extends ChangeHelpRequestRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = HelpRequestContent::rules(false, false);
        foreach ($rules as $field => &$constraints) {
            if (! str_contains($field, '.')) {
                array_unshift($constraints, 'sometimes');
            }
        }
        unset($constraints);
        $rules['code_language'] = array_values(array_diff($rules['code_language'], ['required_with:code']));

        return [...parent::rules(), ...$rules, 'edit_note' => HelpRequestContent::noteRules()];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if (array_intersect(array_keys($this->all()), [...HelpRequestContent::FIELDS, 'help_intent', 'technologies']) === []) {
                $validator->errors()->add('changes', 'Fournissez au moins un champ à modifier.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...HelpRequestContent::messages(), ...parent::messages()];
    }

    public function requestData(): UpdateHelpRequestData
    {
        $data = $this->validated();
        $technologies = isset($data['technologies']) ? array_values(array_map(
            fn (array $technology): array => ['id' => strtolower($technology['id']), 'version_label' => $technology['version_label'] ?? null], $data['technologies'])) : null;

        return new UpdateHelpRequestData($this->version(), array_intersect_key($data, array_flip(HelpRequestContent::FIELDS)),
            isset($data['help_intent']) ? HelpIntent::from($data['help_intent']) : null, $technologies, $data['edit_note'] ?? null);
    }
}
