<?php

namespace App\Http\Requests\HelpRequests;

use App\Data\HelpRequests\HelpRequestFilterData;
use App\Enums\HelpRequests\HelpRequestState;
use App\Http\Requests\PaginatedRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class ListHelpRequestsRequest extends PaginatedRequest
{
    /** @return array<string, array<mixed>|string|ValidationRule> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'scope' => ['sometimes', 'required', Rule::in(['public', 'mine'])],
            'q' => ['nullable', 'string', 'max:200'],
            'technology' => ['sometimes', 'required', 'uuid'],
            'state' => ['sometimes', 'required', Rule::enum(HelpRequestState::class)],
            'sort' => ['sometimes', 'required', Rule::in(HelpRequestFilterData::SORTS)],
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'scope.*' => 'Choisissez la liste publique ou vos demandes.',
            'q.*' => 'La recherche doit être un texte de 200 caractères maximum.',
            'technology.*' => 'La technologie doit être identifiée par un UUID valide.',
            'state.*' => 'Choisissez un état de demande valide.',
            'sort.*' => 'Choisissez un tri autorisé : newest, oldest ou updated.',
        ]);
    }

    public function filters(): HelpRequestFilterData
    {
        $data = $this->validated();

        return new HelpRequestFilterData($this->pageData(), $data['scope'] ?? 'public', $data['q'] ?? null,
            isset($data['technology']) ? strtolower($data['technology']) : null,
            isset($data['state']) ? HelpRequestState::from($data['state']) : null, $data['sort'] ?? 'newest');
    }
}
