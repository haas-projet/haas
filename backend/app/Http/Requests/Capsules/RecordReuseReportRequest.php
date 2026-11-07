<?php

declare(strict_types=1);

namespace App\Http\Requests\Capsules;

use App\Data\Capsules\ReuseReportData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\Capsules\ReuseReportOutcome;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class RecordReuseReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'environment' => ['required', 'string', 'min:1', 'max:200'],
            'reported_at' => ['required', 'date_format:Y-m-d'],
            'outcome' => ['required', Rule::in(array_map(fn (ReuseReportOutcome $o) => $o->value, ReuseReportOutcome::cases()))],
            'observation' => ['required', 'string', 'min:20', 'max:2000'],
        ];
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

    public function toData(): ReuseReportData
    {
        /** @var array{environment:string, reported_at:string, outcome:string, observation:string} $data */
        $data = $this->validated();

        return new ReuseReportData(
            environment: $data['environment'],
            reportedAt: new DateTimeImmutable($data['reported_at']),
            outcome: ReuseReportOutcome::from($data['outcome']),
            observation: $data['observation'],
        );
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey((string) $this->header('Idempotency-Key'));
    }
}
