<?php

declare(strict_types=1);

namespace App\Data\Lab;

use App\Enums\Lab\LabScenario;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ContentValidator;

final readonly class LabSelectionData
{
    public string $versionId;

    public LabScenario $scenario;

    /** @param array<string, mixed> $body */
    public function __construct(array $body)
    {
        $validator = Validator::make($body, [
            'capsule_version_id' => ['required', 'string', 'uuid'],
            'scenario' => ['required', 'string', Rule::enum(LabScenario::class)],
        ]);
        $validator->after(static function (ContentValidator $validator) use ($body): void {
            foreach (array_diff(array_keys($body), ['capsule_version_id', 'scenario']) as $field) {
                $validator->errors()->add($field, 'Ce champ ne peut pas être fourni au laboratoire.');
            }
        });
        $validated = $validator->validate();
        $this->versionId = strtolower($validated['capsule_version_id']);
        $this->scenario = LabScenario::from($validated['scenario']);
    }

    /** @return array{capsule_version_id: string, scenario: string} */
    public function payload(): array
    {
        return ['capsule_version_id' => $this->versionId, 'scenario' => $this->scenario->value];
    }
}
