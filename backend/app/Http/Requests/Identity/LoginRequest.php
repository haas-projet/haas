<?php

namespace App\Http\Requests\Identity;

use App\Data\Identity\LoginData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:255'],
            'password' => ['bail', 'required', 'string', 'max:128'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être utilisé.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email.*' => 'Saisissez une adresse courriel valide.', 'password.*' => 'Saisissez votre mot de passe, de 128 caractères maximum.'];
    }

    public function credentialsData(): LoginData
    {
        $data = $this->validated();

        return new LoginData($data['email'], $data['password']);
    }
}
