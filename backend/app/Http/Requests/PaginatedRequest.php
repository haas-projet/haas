<?php

namespace App\Http\Requests;

use App\Data\Common\PageData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PaginatedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:'.PageData::MAX_PAGE],
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:'.PageData::MAX_PER_PAGE],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'Ce paramètre ne peut pas être utilisé.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'page.*' => 'Le numéro de page doit être un entier positif dans les limites autorisées.',
            'per_page.*' => 'La taille de page doit être un entier entre 1 et 50.',
        ];
    }

    public function pageData(): PageData
    {
        $data = $this->validated();

        return new PageData((int) ($data['page'] ?? 1), (int) ($data['per_page'] ?? PageData::DEFAULT_PER_PAGE));
    }
}
