<?php

namespace App\Http\Requests\Identity;

use App\Data\Identity\ManageAccountData;
use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ManageAccountRequest extends FormRequest
{
    public function member(): User
    {
        return $this->user() instanceof User ? $this->user() : throw new AuthenticationException;
    }

    public function authorize(): bool
    {
        return $this->member()->can('administer', User::class);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer:strict', 'min:0', 'max:2147483646'],
            'role' => ['required_without:status', Rule::prohibitedIf($this->exists('status')), Rule::enum(Role::class)],
            'status' => ['required_without:role', Rule::prohibitedIf($this->exists('role')), Rule::enum(AccountStatus::class)],
            'reason' => ['required', 'string', 'min:20', 'max:1000', 'not_regex:/[\x00-\x1F\x7F]/u'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être modifié.');
            }
        });
    }

    public function decision(): ManageAccountData
    {
        $data = $this->validated();
        $field = array_key_exists('role', $data) ? 'role' : 'status';

        return new ManageAccountData($data['lock_version'], $field, $data[$field], $data['reason']);
    }
}
