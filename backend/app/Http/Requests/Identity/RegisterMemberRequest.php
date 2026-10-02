<?php

namespace App\Http\Requests\Identity;

use App\Data\Identity\RegisterMemberData;
use App\Support\Identity\RegistrationTerms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class RegisterMemberRequest extends FormRequest
{
    // Création publique : aucun acteur ni ressource existante à autoriser par Policy.
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

    /** @return array<string, array<mixed>> */
    public function rules(RegistrationTerms $terms): array
    {
        return [
            'handle' => ['bail', 'required', 'string', 'min:3', 'max:30', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'email' => ['bail', 'required', 'string', 'max:255', 'email:rfc', 'not_regex:/\s/u'],
            'password' => ['bail', 'required', 'string', 'min:12', 'max:128'],
            'password_confirmation' => ['bail', 'required', 'string', 'same:password'],
            'terms_accepted' => ['required', 'boolean:strict', Rule::in([true])],
            'terms_version' => ['bail', 'required', 'string', Rule::in([$terms->currentVersion()])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['handle', 'email', 'password', 'password_confirmation', 'terms_accepted', 'terms_version']) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être utilisé.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'handle.*' => 'Saisissez un pseudonyme de 3 à 30 caractères, sans caractère de contrôle.',
            'email.*' => 'Saisissez une adresse courriel valide de 255 caractères maximum.',
            'password.*' => 'Saisissez un mot de passe de 12 à 128 caractères et confirmez-le à l’identique.',
            'password_confirmation.*' => 'Confirmez votre mot de passe à l’identique.',
            'terms_accepted.*' => 'Acceptez les conditions d’utilisation.',
            'terms_version.*' => 'Consultez et acceptez la version actuelle des conditions d’utilisation.',
        ];
    }

    public function registrationData(): RegisterMemberData
    {
        $data = $this->validated();

        return new RegisterMemberData($data['handle'], $data['email'], $data['password'], $data['terms_version']);
    }
}
