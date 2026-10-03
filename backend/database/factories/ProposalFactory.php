<?php

namespace Database\Factories;

use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Proposal> */
class ProposalFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_id' => HelpRequest::factory(),
            'author_id' => User::factory(),
            'diagnosis' => Str::padRight('Diagnostic détaillé expliquant la cause probable ', 50, 'x'),
            'fix' => Str::padRight('Correctif proposé avec étapes et pré-requis ', 50, 'x'),
            'verification' => Str::padRight('Mode de vérification attendu après application ', 50, 'x'),
            'limits' => Str::padRight('Limites connues du correctif proposé ', 30, 'x'),
            'state' => 'proposed',
        ];
    }
}
