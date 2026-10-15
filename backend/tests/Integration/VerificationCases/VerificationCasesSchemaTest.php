<?php

declare(strict_types=1);

namespace Tests\Integration\VerificationCases;

use App\Enums\VerificationCases\VerificationCaseState;
use App\Models\Capsules\CapsuleVersion;
use App\Models\HelpRequest;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class VerificationCasesSchemaTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_help_request_parent_is_persisted_with_defaults(): void
    {
        $case = VerificationCase::factory()->create();

        $fresh = $case->refresh();
        $this->assertSame(VerificationCaseState::Draft, $fresh->state);
        $this->assertSame(0, $fresh->current_revision);
        $this->assertSame(1, $fresh->lock_version);
        $this->assertNull($fresh->capsule_version_id);
        $this->assertNotNull($fresh->help_request_id);
    }

    public function test_capsule_version_parent_is_accepted_when_help_request_is_null(): void
    {
        $version = CapsuleVersion::factory()->create();

        $case = VerificationCase::factory()->create([
            'help_request_id' => null,
            'capsule_version_id' => $version->id,
        ]);

        $this->assertSame($version->id, $case->refresh()->capsule_version_id);
    }

    public function test_both_parents_simultaneously_are_rejected_by_xor_constraint(): void
    {
        $version = CapsuleVersion::factory()->create();
        $request = HelpRequest::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/verification_cases_parent_xor/i');

        VerificationCase::factory()->create([
            'help_request_id' => $request->id,
            'capsule_version_id' => $version->id,
        ]);
    }

    public function test_absence_of_both_parents_is_rejected_by_xor_constraint(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/verification_cases_parent_xor/i');

        VerificationCase::factory()->create([
            'help_request_id' => null,
            'capsule_version_id' => null,
        ]);
    }

    public function test_state_values_outside_the_whitelist_are_rejected(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/verification_cases_state_values/i');

        DB::table('verification_cases')->insert([
            'id' => (string) Str::uuid(),
            'author_id' => User::factory()->create()->id,
            'help_request_id' => HelpRequest::factory()->create()->id,
            'capsule_version_id' => null,
            'state' => 'invalid',
            'current_revision' => 0,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_current_revision_must_be_zero_when_state_is_draft(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/verification_cases_current_revision_consistent/i');

        DB::table('verification_cases')->insert([
            'id' => (string) Str::uuid(),
            'author_id' => User::factory()->create()->id,
            'help_request_id' => HelpRequest::factory()->create()->id,
            'capsule_version_id' => null,
            'state' => 'draft',
            'current_revision' => 1,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_current_revision_must_be_at_least_one_outside_of_draft(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/verification_cases_current_revision_consistent/i');

        DB::table('verification_cases')->insert([
            'id' => (string) Str::uuid(),
            'author_id' => User::factory()->create()->id,
            'help_request_id' => HelpRequest::factory()->create()->id,
            'capsule_version_id' => null,
            'state' => 'submitted',
            'current_revision' => 0,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_help_request_deletion_is_restricted_while_a_case_references_it(): void
    {
        $request = HelpRequest::factory()->create();
        VerificationCase::factory()->create(['help_request_id' => $request->id]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/foreign key|help_request_id/i');

        $request->delete();
    }

    public function test_capsule_version_deletion_is_restricted_while_a_case_references_it(): void
    {
        $version = CapsuleVersion::factory()->create();
        VerificationCase::factory()->create([
            'help_request_id' => null,
            'capsule_version_id' => $version->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/foreign key|capsule_version_id/i');

        $version->delete();
    }

    public function test_author_deletion_is_restricted_while_a_case_references_them(): void
    {
        $author = User::factory()->create();
        VerificationCase::factory()->create(['author_id' => $author->id]);

        $this->expectException(QueryException::class);

        $author->delete();
    }

    public function test_revisions_are_unique_per_case_and_revision_number(): void
    {
        $case = VerificationCase::factory()->create();
        VerificationCaseRevision::factory()->create([
            'case_id' => $case->id,
            'revision_no' => 1,
            'digest' => str_repeat('a', 64),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/verification_case_revisions_case_revno_unique/i');

        VerificationCaseRevision::factory()->create([
            'case_id' => $case->id,
            'revision_no' => 1,
            'digest' => str_repeat('b', 64),
        ]);
    }

    public function test_revision_digest_is_a_sha256_hex_string(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/verification_case_revisions_digest_shape/i');

        VerificationCaseRevision::factory()->create([
            'digest' => 'not-a-hex-digest',
        ]);
    }

    public function test_revision_body_must_be_a_json_object(): void
    {
        $case = VerificationCase::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/verification_case_revisions_body_object/i');

        DB::table('verification_case_revisions')->insert([
            'id' => (string) Str::uuid(),
            'case_id' => $case->id,
            'revision_no' => 1,
            'body' => json_encode(['a', 'b']),
            'digest' => str_repeat('c', 64),
            'submitted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_revision_submitted_at_cannot_predate_created_at(): void
    {
        $case = VerificationCase::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/verification_case_revisions_submitted_after_created/i');

        DB::table('verification_case_revisions')->insert([
            'id' => (string) Str::uuid(),
            'case_id' => $case->id,
            'revision_no' => 1,
            'body' => json_encode(['title' => 'ok']),
            'digest' => str_repeat('d', 64),
            'submitted_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_revision_number_must_be_at_least_one(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/verification_case_revisions_revision_no_positive/i');

        VerificationCaseRevision::factory()->create([
            'revision_no' => 0,
        ]);
    }

    public function test_case_cannot_be_deleted_while_revisions_reference_it(): void
    {
        $case = VerificationCase::factory()->create();
        VerificationCaseRevision::factory()->create(['case_id' => $case->id]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/foreign key|case_id/i');

        $case->delete();
    }
}
