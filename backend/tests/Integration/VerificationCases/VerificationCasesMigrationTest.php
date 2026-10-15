<?php

declare(strict_types=1);

namespace Tests\Integration\VerificationCases;

use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\PostgresTestCase;

final class VerificationCasesMigrationTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_fresh_migrations_create_both_verification_tables(): void
    {
        $this->assertTrue(Schema::hasTable('verification_cases'));
        $this->assertTrue(Schema::hasTable('verification_case_revisions'));
        $this->assertTrue(Schema::hasColumn('verification_cases', 'state'));
        $this->assertTrue(Schema::hasColumn('verification_cases', 'current_revision'));
        $this->assertTrue(Schema::hasColumn('verification_cases', 'lock_version'));
        $this->assertTrue(Schema::hasColumn('verification_case_revisions', 'digest'));
        $this->assertTrue(Schema::hasColumn('verification_case_revisions', 'body'));
    }

    public function test_down_migrations_remove_both_verification_tables(): void
    {
        $paths = array_map(database_path(...), [
            'migrations/2026_10_15_120000_bv201_create_verification_cases_table.php',
            'migrations/2026_10_15_120100_bv201_create_verification_case_revisions_table.php',
        ]);

        foreach (array_reverse($paths) as $path) {
            $this->assertFileExists($path);
            $migration = require $path;
            $this->assertInstanceOf(Migration::class, $migration);
            (new ReflectionMethod($migration, 'down'))->invoke($migration);
        }

        $this->assertFalse(Schema::hasTable('verification_case_revisions'));
        $this->assertFalse(Schema::hasTable('verification_cases'));
    }

    public function test_incremental_up_down_up_restores_both_verification_tables(): void
    {
        $paths = array_map(database_path(...), [
            'migrations/2026_10_15_120000_bv201_create_verification_cases_table.php',
            'migrations/2026_10_15_120100_bv201_create_verification_case_revisions_table.php',
        ]);

        foreach (array_reverse($paths) as $path) {
            (new ReflectionMethod(require $path, 'down'))->invoke(require $path);
        }
        $this->assertFalse(Schema::hasTable('verification_cases'));
        $this->assertFalse(Schema::hasTable('verification_case_revisions'));

        foreach ($paths as $path) {
            (new ReflectionMethod(require $path, 'up'))->invoke(require $path);
        }
        $this->assertTrue(Schema::hasTable('verification_cases'));
        $this->assertTrue(Schema::hasTable('verification_case_revisions'));
    }

    public function test_fillable_arrays_are_empty_so_mass_assignment_is_rejected(): void
    {
        $this->assertSame([], (new VerificationCase)->getFillable());
        $this->assertSame([], (new VerificationCaseRevision)->getFillable());
    }

    public function test_protected_fields_are_not_mass_assignable_through_fill(): void
    {
        $case = new VerificationCase;
        $case->fill([
            'state' => 'integrated',
            'current_revision' => 42,
            'lock_version' => 99,
            'author_id' => '00000000-0000-4000-8000-000000000001',
            'help_request_id' => '00000000-0000-4000-8000-000000000002',
            'capsule_version_id' => '00000000-0000-4000-8000-000000000003',
        ]);

        $this->assertNull($case->getAttribute('state'));
        $this->assertNull($case->getAttribute('current_revision'));
        $this->assertNull($case->getAttribute('lock_version'));
        $this->assertNull($case->getAttribute('author_id'));
        $this->assertNull($case->getAttribute('help_request_id'));
        $this->assertNull($case->getAttribute('capsule_version_id'));
    }

    public function test_revision_fields_are_not_mass_assignable_through_fill(): void
    {
        $revision = new VerificationCaseRevision;
        $revision->fill([
            'case_id' => '00000000-0000-4000-8000-000000000001',
            'revision_no' => 7,
            'body' => ['title' => 'injection'],
            'digest' => str_repeat('a', 64),
            'submitted_at' => now(),
        ]);

        $this->assertNull($revision->getAttribute('case_id'));
        $this->assertNull($revision->getAttribute('revision_no'));
        $this->assertNull($revision->getAttribute('body'));
        $this->assertNull($revision->getAttribute('digest'));
        $this->assertNull($revision->getAttribute('submitted_at'));
    }
}
