<?php

namespace App\Http\Requests\Identity;

use App\Data\Idempotency\IdempotencyKey;
use App\Data\Identity\UpdateProfileData;
use App\Models\User;
use App\Rules\GithubUrl;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateProfileRequest extends FormRequest
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
        return $this->member()->can('updateProfile', $this->member());
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('bio') && $this->input('bio') === null) {
            $this->merge(['bio' => '']);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer:strict', 'min:0', 'max:2147483646'],
            'bio' => ['sometimes', 'string', 'max:500', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'primary_language' => ['sometimes', 'required', 'string', 'max:35', 'regex:/\A[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})*\z/'],
            'github_url' => ['sometimes', 'nullable', 'string', 'max:2048', new GithubUrl],
            'technology_ids' => ['sometimes', 'array', 'list', 'max:8'],
            'technology_ids.*' => ['bail', 'required', 'string', 'uuid', 'distinct:ignore_case'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $key = $this->header('Idempotency-Key');
            if ($key !== null && ! IdempotencyKey::valid($key)) {
                $validator->errors()->add('Idempotency-Key', 'Utilisez un UUID v4 pour identifier cette intention.');
            }
            $allowed = ['lock_version', 'bio', 'country', 'primary_language', 'github_url', 'technology_ids'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être modifié.');
            }
            if (array_intersect(array_keys($this->all()), array_slice($allowed, 1)) === []) {
                $validator->errors()->add('profile', 'Indiquez au moins un champ à modifier.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lock_version.*' => 'Rechargez la version actuelle du profil avant de le modifier.',
            'bio.*' => 'La biographie doit contenir au maximum 500 caractères de texte.',
            'country.*' => 'Le pays est facultatif et limité à 100 caractères.',
            'primary_language.*' => 'Indiquez un code de langue valide, par exemple fr ou pt-BR.',
            'github_url.*' => 'Utilisez un lien GitHub HTTPS valide de 2048 caractères maximum.',
            'technology_ids.*' => 'Choisissez au maximum huit technologies distinctes.',
            'technology_ids.*.*' => 'Chaque technologie doit avoir un identifiant UUID distinct.',
        ];
    }

    public function profileData(): UpdateProfileData
    {
        $data = $this->validated();

        return new UpdateProfileData($data['lock_version'], array_intersect_key($data, array_flip(['bio', 'country', 'primary_language', 'github_url'])),
            isset($data['technology_ids']) ? array_values(array_map('strtolower', $data['technology_ids'])) : null);
    }

    public function idempotencyKey(): ?IdempotencyKey
    {
        $key = $this->header('Idempotency-Key');

        return $key === null ? null : new IdempotencyKey($key);
    }
}
