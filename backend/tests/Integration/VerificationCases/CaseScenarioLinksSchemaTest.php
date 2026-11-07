<?php

declare(strict_types=1);

namespace Tests\Integration\VerificationCases;

use App\Enums\VerificationCases\VerificationCaseState;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class CaseScenarioLinksSchemaTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_scenario_values_outside_the_whitelist_are_rejected(): void
    {
        $revision = $this->seedRevision();
        $reviewer = User::factory()->verified()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/case_scenario_links_scenario_values/i');

        DB::table('case_scenario_links')->insert([
            'id' => (string) Str::uuid(),
            'case_revision_id' => $revision->id,
            'scenario_key' => 'unknown_scenario',
            'suite_digest' => str_repeat('a', 64),
            'release_commit' => str_repeat('b', 40),
            'reviewer_id' => $reviewer->id,
            'integrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_revision_unique_binding(): void
    {
        $revision = $this->seedRevision();
        $reviewer = User::factory()->verified()->create();
        $this->insertLink($revision->id, $reviewer->id, 'nominal_event');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/unique|case_revision_id/i');

        $this->insertLink($revision->id, $reviewer->id, 'duplicate_event');
    }

    public function test_release_commit_must_be_hex_40(): void
    {
        $revision = $this->seedRevision();
        $reviewer = User::factory()->verified()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/release_commit_shape/i');

        DB::table('case_scenario_links')->insert([
            'id' => (string) Str::uuid(),
            'case_revision_id' => $revision->id,
            'scenario_key' => 'nominal_event',
            'suite_digest' => str_repeat('a', 64),
            'release_commit' => 'not-a-sha',
            'reviewer_id' => $reviewer->id,
            'integrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_suite_digest_must_be_hex_64(): void
    {
        $revision = $this->seedRevision();
        $reviewer = User::factory()->verified()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/suite_digest_shape/i');

        DB::table('case_scenario_links')->insert([
            'id' => (string) Str::uuid(),
            'case_revision_id' => $revision->id,
            'scenario_key' => 'nominal_event',
            'suite_digest' => 'short',
            'release_commit' => str_repeat('b', 40),
            'reviewer_id' => $reviewer->id,
            'integrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_revision_cannot_be_deleted_while_link_exists(): void
    {
        $revision = $this->seedRevision();
        $reviewer = User::factory()->verified()->create();
        $this->insertLink($revision->id, $reviewer->id, 'nominal_event');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/foreign key|case_revision_id/i');

        $revision->delete();
    }

    public function test_reviewer_cannot_be_deleted_while_link_exists(): void
    {
        $revision = $this->seedRevision();
        $reviewer = User::factory()->verified()->create();
        $this->insertLink($revision->id, $reviewer->id, 'nominal_event');

        $this->expectException(QueryException::class);

        $reviewer->delete();
    }

    private function seedRevision(): VerificationCaseRevision
    {
        $case = VerificationCase::factory()->create(['state' => VerificationCaseState::Reviewed, 'current_revision' => 1]);

        return VerificationCaseRevision::factory()->create([
            'case_id' => $case->id,
            'revision_no' => 1,
            'created_at' => now()->subMinute(),
            'submitted_at' => now(),
        ]);
    }

    private function insertLink(string $revisionId, string $reviewerId, string $scenario): void
    {
        DB::table('case_scenario_links')->insert([
            'id' => (string) Str::uuid(),
            'case_revision_id' => $revisionId,
            'scenario_key' => $scenario,
            'suite_digest' => str_repeat('a', 64),
            'release_commit' => str_repeat('b', 40),
            'reviewer_id' => $reviewerId,
            'integrated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
