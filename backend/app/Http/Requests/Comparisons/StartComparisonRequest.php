<?php

declare(strict_types=1);

namespace App\Http\Requests\Comparisons;

use App\Data\Comparisons\StartComparisonData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\Lab\LabScenario;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * BV206 — POST /comparisons. Entrées strictes CAHIER §44 ligne 1645 :
 * seulement `comparison_profile_id` et `scenario_key` d'une liste
 * autorisée. Pas de code, URL, commande, chemin, nom de classe, scénario
 * dynamique ou paramètre libre. Toute clé non attendue est rejetée en 422.
 */
final class StartComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'comparison_profile_id' => ['required', 'string', 'uuid'],
            'scenario_key' => ['required', 'string', Rule::enum(LabScenario::class)],
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
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être envoyé à la comparaison.');
            }
        });
    }

    public function toData(): StartComparisonData
    {
        /** @var array{comparison_profile_id: string, scenario_key: string} $validated */
        $validated = $this->validated();

        return new StartComparisonData(
            comparisonProfileId: strtolower($validated['comparison_profile_id']),
            scenario: LabScenario::from($validated['scenario_key']),
        );
    }

    public function idempotencyKey(): string
    {
        return (string) $this->header('Idempotency-Key');
    }
}
