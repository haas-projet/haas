<?php

declare(strict_types=1);

namespace App\Http\Requests\VerificationCases;

use App\Data\Idempotency\IdempotencyKey;
use App\Data\VerificationCases\SubmitVerificationCaseData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SubmitVerificationCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1', 'max:2147483646'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['lock_version']) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être utilisé.');
            }
            $raw = $this->header('Idempotency-Key');
            if ($raw === null || $raw === '') {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence est requise.');
            } elseif (! IdempotencyKey::valid((string) $raw)) {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence doit être un UUID v4.');
            }
        });
    }

    public function toSubmit(): SubmitVerificationCaseData
    {
        /** @var array{lock_version:int} $v */
        $v = $this->validated();

        return new SubmitVerificationCaseData((int) $v['lock_version']);
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey((string) $this->header('Idempotency-Key'));
    }
}
