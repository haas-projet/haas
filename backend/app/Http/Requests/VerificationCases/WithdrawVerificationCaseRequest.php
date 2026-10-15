<?php

declare(strict_types=1);

namespace App\Http\Requests\VerificationCases;

use App\Data\Idempotency\IdempotencyKey;
use App\Data\VerificationCases\WithdrawVerificationCaseData;
use App\Rules\NoLikelySecret;
use App\Rules\TrimmedMinimumLength;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class WithdrawVerificationCaseRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:10', 'max:500', new TrimmedMinimumLength(10), 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['lock_version', 'reason']) as $field) {
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

    public function toWithdraw(): WithdrawVerificationCaseData
    {
        /** @var array{lock_version:int,reason:string} $v */
        $v = $this->validated();

        return new WithdrawVerificationCaseData((int) $v['lock_version'], (string) $v['reason']);
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey((string) $this->header('Idempotency-Key'));
    }
}
