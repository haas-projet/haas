<?php

namespace Database\Factories;

use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Proposal> */
class ProposalFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_id' => HelpRequest::factory(),
            'author_id' => User::factory(),
            'diagnosis' => 'Diagnostic détaillé expliquant la cause probable.',
            'fix' => 'Correctif proposé avec étapes et pré-requis.',
            'verification' => 'Mode de vérification attendu après application.',
            'limits' => 'Limites connues du correctif proposé.',
            'state' => 'proposed',
        ];
    }
}
