<?php

declare(strict_types=1);

namespace App\Http\Requests\Lab;

use App\Data\Idempotency\IdempotencyKey;
use App\Data\Lab\StartLabRunData;
use App\Enums\Lab\LabScenario;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StartLabRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'capsule_version_id' => ['required', 'string', 'uuid'],
            'scenario' => ['required', 'string', Rule::enum(LabScenario::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $raw = $this->header('Idempotency-Key');
            if ($raw === null || $raw === '') {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence est requise.');
            } elseif (! IdempotencyKey::valid($raw)) {
                $validator->errors()->add('idempotency_key', 'La clé d’idempotence doit être un UUID v4.');
            }
            // Refus explicite — pas d'ignorance silencieuse — de tout champ
            // additionnel, en particulier `code`, `command`, `url` : §26 et AC18.
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être envoyé au laboratoire.');
            }
        });
    }

    public function toData(): StartLabRunData
    {
        /** @var array{capsule_version_id: string, scenario: string} $validated */
        $validated = $this->validated();

        return new StartLabRunData(
            versionId: strtolower($validated['capsule_version_id']),
            scenario: LabScenario::from($validated['scenario']),
        );
    }

    public function idempotencyKey(): string
    {
        return (string) $this->header('Idempotency-Key');
    }
}
