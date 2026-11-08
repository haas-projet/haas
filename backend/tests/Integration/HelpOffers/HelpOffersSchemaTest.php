<?php

namespace Tests\Integration\HelpOffers;

use App\Enums\HelpOffers\HelpContributionCategory;
use App\Enums\HelpOffers\HelpOfferState;
use App\Models\HelpOffer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class HelpOffersSchemaTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_schema_defaults_are_private_and_typed(): void
    {
        $this->assertTrue(Schema::hasTable('help_offers'));
        $project = Project::factory()->create();
        $proposer = User::factory()->verified()->create();
        $offer = HelpOffer::forceCreate([
            'project_id' => $project->id,
            'proposer_id' => $proposer->id,
            'category' => HelpContributionCategory::Documentation,
            'public_summary' => 'Je peux relire la procédure sur un téléphone.',
            'expected_outcome' => 'Une liste de corrections reproductibles.',
            'expires_at' => now()->addDays(7),
        ]);
        $this->assertTrue(Str::isUuid($offer->id));
        $this->assertSame(HelpOfferState::Pending, $offer->state);
        $this->assertFalse($offer->allow_public_summary);
        $this->assertFalse($project->refresh()->help_open);
        $this->assertNull($project->help_categories);
    }

    public function test_pending_offer_is_unique_per_project_and_proposer(): void
    {
        $project = Project::factory()->create();
        $proposer = User::factory()->verified()->create();
        $attributes = [
            'project_id' => $project->id, 'proposer_id' => $proposer->id,
            'category' => 'documentation', 'public_summary' => 'Je peux relire la procédure sur un téléphone.',
            'expected_outcome' => 'Une liste de corrections reproductibles.', 'expires_at' => now()->addDays(7),
        ];
        HelpOffer::forceCreate($attributes);
        $this->expectException(QueryException::class);
        HelpOffer::forceCreate($attributes);
    }

    public function test_categories_and_private_preferences_are_constrained(): void
    {
        $project = Project::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('projects')->where('id', $project->id)->update(['help_categories' => json_encode(['unknown'])]);
    }

    public function test_rollback_refuses_known_offers(): void
    {
        $migration = require database_path('migrations/2026_11_07_200000_bh01_help_offers.php');
        $project = Project::factory()->create();
        HelpOffer::forceCreate([
            'project_id' => $project->id, 'proposer_id' => User::factory()->verified()->create()->id,
            'category' => 'documentation', 'public_summary' => 'Je peux relire la procédure sur un téléphone.',
            'expected_outcome' => 'Une liste de corrections reproductibles.', 'expires_at' => now()->addDays(7),
        ]);
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
