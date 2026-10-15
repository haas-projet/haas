<?php

declare(strict_types=1);

namespace Database\Factories\VerificationCases;

use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VerificationCaseRevision> */
final class VerificationCaseRevisionFactory extends Factory
{
    protected $model = VerificationCaseRevision::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $body = [
            'title' => 'Vérifier le comportement du compteur après rejeu identique',
            'context' => "Le composant reçoit la même commande deux fois à cause d'un rejeu réseau. Le cas s'écrit sans l'exécuter.",
            'steps' => "1. Soumettre une commande identifiée par une clé.\n2. Rejouer avec la même clé.\n3. Observer que le compteur n'augmente qu'une fois.",
            'expected' => "Le compteur avance d'une unité au premier appel et reste stable au rejeu.",
            'observed' => null,
            'environment' => 'Documentaire — aucune exécution demandée.',
        ];

        return [
            'case_id' => VerificationCase::factory(),
            'revision_no' => 1,
            'body' => $body,
            'digest' => str_repeat('0', 64),
            'submitted_at' => null,
        ];
    }
}
