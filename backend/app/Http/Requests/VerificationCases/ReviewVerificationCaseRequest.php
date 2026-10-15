<?php

declare(strict_types=1);

namespace App\Http\Requests\VerificationCases;

use App\Data\Idempotency\IdempotencyKey;
use App\Data\VerificationCases\ReviewVerificationCaseData;
use App\Enums\VerificationCases\ReviewDecision;
use App\Rules\NoLikelySecret;
use App\Rules\TrimmedMinimumLength;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ReviewVerificationCaseRequest extends FormRequest
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
            'decision' => ['required', 'string', Rule::enum(ReviewDecision::class)],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000', new TrimmedMinimumLength(10), 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', new NoLikelySecret],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['lock_version', 'decision', 'reason']) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être utilisé.');
            }
            $raw = $this->header('Idempotency-Key');
            if ($raw === null || $raw === '') {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence est requise.');
            } elseif (! IdempotencyKey::valid((string) $raw)) {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence doit être un UUID v4.');
            }
            if ($this->input('decision') === ReviewDecision::Declined->value && ! $this->filled('reason')) {
                $validator->errors()->add('reason', 'Un refus doit porter un motif explicite.');
            }
        });
    }

    public function toReview(): ReviewVerificationCaseData
    {
        /** @var array{lock_version:int,decision:string,reason:?string} $v */
        $v = $this->validated();

        return new ReviewVerificationCaseData(
            (int) $v['lock_version'],
            ReviewDecision::from($v['decision']),
            $v['reason'] ?? null,
        );
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey((string) $this->header('Idempotency-Key'));
    }
}
