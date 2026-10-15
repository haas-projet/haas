<?php

declare(strict_types=1);

namespace Tests\Integration\VerificationCases;

use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use Illuminate\Database\Eloquent\MassAssignmentException;
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
        // require d'un fichier retourne son `new class` à la première inclusion
        // uniquement ; les requires suivants retournent `true`. On conserve les
        // instances dans des variables pour les réutiliser lors des deux passes.
        $casesMigration = require database_path('migrations/2026_10_15_120000_bv201_create_verification_cases_table.php');
        $revisionsMigration = require database_path('migrations/2026_10_15_120100_bv201_create_verification_case_revisions_table.php');

        (new ReflectionMethod($revisionsMigration, 'down'))->invoke($revisionsMigration);
        (new ReflectionMethod($casesMigration, 'down'))->invoke($casesMigration);
        $this->assertFalse(Schema::hasTable('verification_cases'));
        $this->assertFalse(Schema::hasTable('verification_case_revisions'));

        (new ReflectionMethod($casesMigration, 'up'))->invoke($casesMigration);
        (new ReflectionMethod($revisionsMigration, 'up'))->invoke($revisionsMigration);
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

        // $fillable = [] + $guarded = ['*'] (défaut) → totallyGuarded() : fill()
        // lève MassAssignmentException au premier champ. Aucune voie publique
        // d'affectation en masse n'est tolérée côté modèle.
        $this->expectException(MassAssignmentException::class);
        $case->fill([
            'state' => 'integrated',
            'current_revision' => 42,
            'lock_version' => 99,
            'author_id' => '00000000-0000-4000-8000-000000000001',
            'help_request_id' => '00000000-0000-4000-8000-000000000002',
            'capsule_version_id' => '00000000-0000-4000-8000-000000000003',
        ]);
    }

    public function test_revision_fields_are_not_mass_assignable_through_fill(): void
    {
        $revision = new VerificationCaseRevision;

        $this->expectException(MassAssignmentException::class);
        $revision->fill([
            'case_id' => '00000000-0000-4000-8000-000000000001',
            'revision_no' => 7,
            'body' => ['title' => 'injection'],
            'digest' => str_repeat('a', 64),
            'submitted_at' => now(),
        ]);
    }
}
