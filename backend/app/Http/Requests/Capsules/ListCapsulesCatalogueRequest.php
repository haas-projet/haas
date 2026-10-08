<?php

declare(strict_types=1);

namespace App\Http\Requests\Capsules;

use App\Data\Capsules\CapsuleCatalogueFilterData;
use App\Http\Requests\PaginatedRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtre public du catalogue (B26).
 *
 * `page_size` par défaut 20, maximum 50 (`PaginatedRequest`).
 * `sort` en liste blanche ; `relevance` sans `q` est refusée en 422
 * côté `CapsuleCatalogueFilterData`.
 */
final class ListCapsulesCatalogueRequest extends PaginatedRequest
{
    /** @return array<string, array<mixed>|string|ValidationRule> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'q' => ['nullable', 'string', 'max:200'],
            'technology' => ['sometimes', 'required', 'uuid'],
            'sort' => ['sometimes', 'required', Rule::in(CapsuleCatalogueFilterData::SORTS)],
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'q.*' => 'La recherche doit être un texte de 200 caractères maximum.',
            'technology.*' => 'La technologie doit être identifiée par un UUID valide.',
            'sort.*' => 'Choisissez un tri autorisé : date ou relevance.',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            $sort = (string) $this->input('sort', 'date');
            $search = trim((string) $this->input('q', ''));
            if ($sort === 'relevance' && $search === '') {
                $validator->errors()->add('sort', 'Le tri par pertinence exige une recherche textuelle.');
            }
        });
    }

    public function filters(): CapsuleCatalogueFilterData
    {
        /** @var array{q?: string, technology?: string, sort?: string} $data */
        $data = $this->validated();
        $search = isset($data['q']) ? trim((string) $data['q']) : null;

        return new CapsuleCatalogueFilterData(
            page: $this->pageData(),
            search: $search === '' ? null : $search,
            technology: isset($data['technology']) ? strtolower($data['technology']) : null,
            sort: $data['sort'] ?? 'date',
        );
    }
}
