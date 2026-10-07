<?php

declare(strict_types=1);

namespace App\Http\Requests\Capsules;

use App\Data\Capsules\ReviewCommandData;
use App\Data\Idempotency\IdempotencyKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class CapsuleReviewCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['lock_version' => ['required', 'integer', 'min:1', 'max:2147483646']];
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

    public function command(): ReviewCommandData
    {
        /** @var array{lock_version: int, note?: string} $data */
        $data = $this->validated();

        return new ReviewCommandData($data['lock_version'], $data['note'] ?? null);
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey((string) $this->header('Idempotency-Key'));
    }
}
