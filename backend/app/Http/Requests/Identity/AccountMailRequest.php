<?php

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

abstract class AccountMailRequest extends FormRequest
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
    abstract public function rules(): array;

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
        return [
            'email.*' => 'Saisissez une adresse courriel valide de 255 caractères maximum.',
            'password.*' => 'Saisissez un mot de passe de 12 à 128 caractères.',
            'password_confirmation.*' => 'Confirmez votre mot de passe à l’identique.',
            'token.*' => 'Ce lien ne permet pas de réinitialiser le mot de passe. Demandez un nouveau lien.',
            'expires.*' => 'Le lien de vérification est invalide.',
            'signature.*' => 'Le lien de vérification est invalide.',
        ];
    }
}
