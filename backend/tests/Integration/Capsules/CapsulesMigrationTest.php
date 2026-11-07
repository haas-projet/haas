<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\PostgresTestCase;

final class CapsulesMigrationTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_fresh_migrations_create_all_capsule_schema_tables(): void
    {
        $this->assertTrue(Schema::hasTable('capsules'));
        $this->assertTrue(Schema::hasTable('capsule_versions'));
        $this->assertTrue(Schema::hasTable('capsule_contributors'));
        $this->assertTrue(Schema::hasTable('artifacts'));
        $this->assertTrue(Schema::hasTable('capsule_version_technologies'));
    }

    public function test_capsules_schema_has_no_foreign_key_toward_lab_or_test_tables(): void
    {
        $foreignKeys = DB::select(<<<'SQL'
            SELECT
                tc.table_name AS source_table,
                kcu.column_name AS source_column,
                ccu.table_name AS target_table
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
                AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_name IN ('capsules','capsule_versions','capsule_contributors','artifacts','capsule_version_technologies')
            ORDER BY source_table, source_column
        SQL);

        $targets = array_map(static fn ($row) => $row->target_table, $foreignKeys);
        $forbidden = ['lab_definitions', 'lab_runs', 'lab_results', 'test_events', 'test_orders', 'demo_orders'];
        foreach ($forbidden as $table) {
            $this->assertNotContains($table, $targets, "Capsule/brique/laboratoire doivent rester distincts : aucune FK vers {$table}.");
        }

        $sources = array_map(static fn ($row) => $row->source_table.'.'.$row->source_column, $foreignKeys);
        sort($sources);
        $this->assertSame(
            [
                'artifacts.version_id',
                'capsule_contributors.user_id',
                'capsule_contributors.version_id',
                'capsule_version_technologies.technology_id',
                'capsule_version_technologies.version_id',
                'capsule_versions.capsule_id',
                'capsule_versions.reviewer_id',
                'capsules.owner_id',
                'capsules.source_request_id',
            ],
            $sources,
            'FK attendues du schéma B22 : vers users, vers help_requests (source), et vers l\'intérieur du domaine capsules.'
        );
    }

    public function test_down_rollback_removes_all_capsule_schema_tables(): void
    {
        $paths = array_map(database_path(...), [
            'migrations/2026_10_07_013053_create_capsules_table.php',
            'migrations/2026_10_07_013654_create_capsule_versions_table.php',
            'migrations/2026_10_07_014031_create_capsule_contributors_table.php',
            'migrations/2026_10_07_014215_create_artifacts_table.php',
            'migrations/2026_10_07_123901_create_capsule_version_technologies_table.php',
            'migrations/2026_10_07_172331_add_lock_version_to_capsule_versions_table.php',
            'migrations/2026_10_07_200000_b22_preserve_published_capsule_versions.php',
        ]);
        foreach (array_reverse($paths) as $path) {
            $this->assertFileExists($path);
            $migration = require $path;
            $this->assertInstanceOf(Migration::class, $migration);
            (new ReflectionMethod($migration, 'down'))->invoke($migration);
        }

        $this->assertFalse(Schema::hasTable('capsule_version_technologies'));
        $this->assertFalse(Schema::hasTable('artifacts'));
        $this->assertFalse(Schema::hasTable('capsule_contributors'));
        $this->assertFalse(Schema::hasTable('capsule_versions'));
        $this->assertFalse(Schema::hasTable('capsules'));
    }

    public function test_schema_includes_help_requests_table_as_the_source_target(): void
    {
        // Après fusion de B11 : la table help_requests est présente, la FK capsules.source_request_id l'utilise.
        $this->assertTrue(Schema::hasTable('help_requests'));
        $this->assertTrue(Schema::hasTable('capsules'));
        $this->assertTrue(Schema::hasColumn('capsules', 'source_request_id'));
    }

    public function test_source_request_id_is_uuid_type(): void
    {
        $capsulesColumns = Schema::getColumns('capsules');
        $sourceRequestColumn = collect($capsulesColumns)->firstWhere('name', 'source_request_id');
        $this->assertNotNull($sourceRequestColumn);
        $this->assertSame('uuid', $sourceRequestColumn['type_name']);
    }

    public function test_additional_integration_test_is_in_place(): void
    {
        // Témoin : l'ajout d'une nouvelle colonne UUID ne doit pas casser le test précédent.
        $this->assertTrue(Schema::hasColumn('capsules', 'owner_id'));
        $this->assertTrue(Schema::hasColumn('capsule_versions', 'reviewer_id'));
        $this->assertTrue(Schema::hasColumn('artifacts', 'notices_path'));
    }

    public function test_blueprint_cannot_reference_lab_tables_from_capsules(): void
    {
        // Garde-fou : si quelqu'un ajoute un Schema::table('capsules', fn(Blueprint $t) => $t->foreignUuid('lab_run_id')...)
        // le test suivant devra être adapté manuellement.
        $this->assertFalse(Schema::hasColumn('capsules', 'lab_run_id'));
        $this->assertFalse(Schema::hasColumn('capsule_versions', 'lab_run_id'));
        $this->assertFalse(Schema::hasColumn('artifacts', 'lab_run_id'));
        $this->assertInstanceOf(Blueprint::class, new Blueprint(DB::connection(), 'capsules'));
    }
}
