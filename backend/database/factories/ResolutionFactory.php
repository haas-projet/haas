<?php

namespace Database\Factories;

use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Resolution> */
class ResolutionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $request = HelpRequest::factory()->create();
        $proposal = Proposal::factory()->create(['request_id' => $request->id]);

        return [
            'request_id' => $request->id,
            'proposal_id' => $proposal->id,
            'accepted_by' => User::factory(),
            'validation_note' => 'La proposition a résolu le blocage dans mon contexte.',
            'accepted_at' => CarbonImmutable::now(),
            'revoked_at' => null,
        ];
    }
}
