<?php

namespace Database\Factories;

use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/** @extends Factory<Resolution> */
class ResolutionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'request_id' => fn (array $attributes): string => (string) DB::table('proposals')->where('id', $attributes['proposal_id'])->value('request_id'),
            'accepted_by' => User::factory(),
            'validation_note' => 'La proposition a résolu le blocage dans mon contexte.',
            'accepted_at' => CarbonImmutable::now(),
            'revoked_at' => null,
        ];
    }
}
