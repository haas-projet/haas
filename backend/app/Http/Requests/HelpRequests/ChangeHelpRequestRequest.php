<?php

namespace App\Http\Requests\HelpRequests;

use App\Data\Idempotency\IdempotencyKey;
use App\Models\User;
use App\Queries\HelpRequests\FindVisibleHelpRequestQuery;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChangeHelpRequestRequest extends FormRequest
{
    public function member(): User
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        return $actor;
    }

    public function requestId(): string
    {
        return strtolower((string) $this->route('id'));
    }

    public function authorize(): bool
    {
        $target = app(FindVisibleHelpRequestQuery::class)->get($this->member(), $this->requestId());

        return $this->member()->can('update', $target);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['lock_version' => ['required', 'integer', 'min:1', 'max:2147483646']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! IdempotencyKey::valid($this->header('Idempotency-Key', ''))) {
                $validator->errors()->add('Idempotency-Key', 'Utilisez un UUID v4 par intention et conservez-le pour les relances.');
            }
            foreach (array_diff(array_keys($this->all()), array_filter(array_keys($this->rules()), fn (string $field): bool => ! str_contains($field, '.'))) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être fourni.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['lock_version.*' => 'Indiquez une version entière entre 1 et 2147483646.'];
    }

    public function version(): int
    {
        return (int) $this->validated('lock_version');
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey($this->header('Idempotency-Key', ''));
    }
}
