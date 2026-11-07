<?php

declare(strict_types=1);

namespace App\Http\Requests\Capsules;

use App\Data\Capsules\PublishCommandData;
use App\Data\Idempotency\IdempotencyKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Entrée de `POST /api/v1/admin/capsules/{capsule}/versions/{version}/publish`.
 * Seul `lock_version` est accepté ; la clé d'idempotence est en-tête.
 * La note documentaire n'est pas acceptée : la décision `approved` est
 * sans prose côté API.
 */
final class PublishCapsuleVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['lock_version' => ['required', 'integer:strict', 'min:1', 'max:2147483646']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être utilisé.');
            }
            if (! IdempotencyKey::valid((string) $this->header('Idempotency-Key', ''))) {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence doit être un UUID v4.');
            }
        });
    }

    public function command(): PublishCommandData
    {
        /** @var array{lock_version:int} $data */
        $data = $this->validated();

        return new PublishCommandData($data['lock_version']);
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey((string) $this->header('Idempotency-Key'));
    }
}
