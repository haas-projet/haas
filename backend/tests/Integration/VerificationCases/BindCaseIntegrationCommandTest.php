<?php

declare(strict_types=1);

namespace Tests\Integration\VerificationCases;

use App\Enums\VerificationCases\VerificationCaseState;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;

final class BindCaseIntegrationCommandTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_bind_case_links_a_reviewed_case_to_a_scenario_and_marks_it_integrated(): void
    {
        [$case, $revision, $reviewer, $digest] = $this->seedReviewedCase();
        $path = $this->writeManifest($revision->id, $reviewer->id, $digest, 'nominal_event');

        $code = Artisan::call('lab:bind-case', ['manifest' => $path]);
        $this->assertSame(0, $code, Artisan::output());
        $this->assertDatabaseCount('case_scenario_links', 1);
        $this->assertSame(VerificationCaseState::Integrated, $case->refresh()->state);
    }

    public function test_bind_case_is_idempotent_when_rerun_on_the_same_manifest(): void
    {
        [$case, $revision, $reviewer, $digest] = $this->seedReviewedCase();
        $path = $this->writeManifest($revision->id, $reviewer->id, $digest, 'nominal_event');

        $first = Artisan::call('lab:bind-case', ['manifest' => $path]);
        $second = Artisan::call('lab:bind-case', ['manifest' => $path]);
        $this->assertSame(0, $first);
        $this->assertSame(0, $second);
        $this->assertDatabaseCount('case_scenario_links', 1);
    }

    public function test_scenario_out_of_the_whitelist_is_refused(): void
    {
        [$case, $revision, $reviewer, $digest] = $this->seedReviewedCase();
        $path = $this->writeManifest($revision->id, $reviewer->id, $digest, 'unknown_scenario');

        $this->assertSame(1, Artisan::call('lab:bind-case', ['manifest' => $path]));
        $this->assertDatabaseCount('case_scenario_links', 0);
    }

    public function test_digest_incoherent_with_the_revision_is_refused(): void
    {
        [$case, $revision, $reviewer] = $this->seedReviewedCase();
        $path = $this->writeManifest($revision->id, $reviewer->id, str_repeat('f', 64), 'nominal_event');

        $this->assertSame(1, Artisan::call('lab:bind-case', ['manifest' => $path]));
        $this->assertDatabaseCount('case_scenario_links', 0);
    }

    public function test_manifest_out_of_template_shape_is_refused(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bv203-bad-');
        file_put_contents($path, '{"wrong_root": true}');

        $this->assertSame(1, Artisan::call('lab:bind-case', ['manifest' => $path]));
        @unlink($path);
    }

    public function test_case_not_reviewed_is_refused(): void
    {
        [$case, $revision, $reviewer, $digest] = $this->seedReviewedCase();
        // Force the case back to submitted.
        DB::table('verification_cases')->where('id', $case->id)->update(['state' => VerificationCaseState::Submitted->value]);
        $path = $this->writeManifest($revision->id, $reviewer->id, $digest, 'nominal_event');

        $this->assertSame(1, Artisan::call('lab:bind-case', ['manifest' => $path]));
        $this->assertDatabaseCount('case_scenario_links', 0);
    }

    public function test_old_binding_is_preserved_when_a_new_revision_is_submitted(): void
    {
        [$case, $revision1, $reviewer, $digest1] = $this->seedReviewedCase();
        $path = $this->writeManifest($revision1->id, $reviewer->id, $digest1, 'nominal_event');
        $this->assertSame(0, Artisan::call('lab:bind-case', ['manifest' => $path]));

        // Nouvelle révision soumise après intégration.
        $revision2 = VerificationCaseRevision::factory()->create([
            'case_id' => $case->id,
            'revision_no' => 2,
            'submitted_at' => now(),
        ]);
        DB::table('verification_cases')->where('id', $case->id)->update([
            'state' => VerificationCaseState::Submitted->value,
            'current_revision' => 2,
        ]);

        $this->assertDatabaseCount('case_scenario_links', 1);
        $link = DB::table('case_scenario_links')->where('case_revision_id', $revision1->id)->first();
        $this->assertNotNull($link);
        $this->assertSame($digest1, $link->suite_digest);
    }

    /** @return array{0: VerificationCase, 1: VerificationCaseRevision, 2: User, 3: string} */
    private function seedReviewedCase(): array
    {
        $author = User::factory()->verified()->create();
        $reviewer = User::factory()->verified()->create();
        $digest = str_repeat('a', 64);
        $case = VerificationCase::factory()->create([
            'author_id' => $author->id,
            'state' => VerificationCaseState::Reviewed,
            'current_revision' => 1,
        ]);
        $revision = VerificationCaseRevision::factory()->create([
            'case_id' => $case->id,
            'revision_no' => 1,
            'digest' => $digest,
            'created_at' => now()->subMinute(),
            'submitted_at' => now(),
        ]);

        return [$case, $revision, $reviewer, $digest];
    }

    private function writeManifest(string $revisionId, string $reviewerId, string $digest, string $scenario): string
    {
        $data = [
            'verification_cases' => [
                'release_commit' => str_repeat('c', 40),
                'bindings' => [[
                    'case_revision_id' => $revisionId,
                    'scenario_key' => $scenario,
                    'suite_digest' => $digest,
                    'reviewer_id' => $reviewerId,
                ]],
            ],
        ];
        $path = tempnam(sys_get_temp_dir(), 'bv203-');
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));

        return $path;
    }
}
