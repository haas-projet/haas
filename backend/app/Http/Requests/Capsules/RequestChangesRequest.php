<?php

declare(strict_types=1);

namespace App\Http\Requests\Capsules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class RequestChangesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:20', 'max:2000', 'regex:/\A\S(?:.*\S)?\z/u'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['note']) as $forbidden) {
                $validator->errors()->add($forbidden, 'Ce champ ne peut pas être utilisé.');
            }
        });
    }

    public function note(): string
    {
        /** @var array{note:string} $v */
        $v = $this->validated();

        return $v['note'];
    }
}
