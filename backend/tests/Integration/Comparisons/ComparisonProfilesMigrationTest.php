<?php

declare(strict_types=1);

namespace Tests\Integration\Comparisons;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\PostgresTestCase;
use Tests\Support\Mdev44\DomainTables;

final class ComparisonProfilesMigrationTest extends PostgresTestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        try {
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_fresh_migrations_create_comparison_profiles_table(): void
    {
        $this->assertTrue(Schema::connection('pgsql')->hasTable('comparison_profiles'));
        $this->assertTrue(Schema::connection('pgsql')->hasColumn('comparison_profiles', 'baseline_manifest'));
        $this->assertTrue(Schema::connection('pgsql')->hasColumn('comparison_profiles', 'enabled'));
    }

    public function test_down_removes_the_table_tolerantly(): void
    {
        // BV205 ajoute comparison_runs (FK vers comparison_profiles) et un
        // lien lab_runs.comparison_id. Le down() isolé de BV204 n'est joué
        // qu'après les down() de BV205 ; on respecte cet ordre ici.
        $this->rollbackBv205();
        $path = database_path('migrations/2026_10_15_120300_bv204_create_comparison_profiles_table.php');
        $this->assertFileExists($path);
        $migration = require $path;
        $this->assertInstanceOf(Migration::class, $migration);

        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $this->assertFalse(Schema::connection('pgsql')->hasTable('comparison_profiles'));

        // Tolérance : rejouer down() alors que la table est absente n'échoue pas.
        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $this->assertFalse(Schema::connection('pgsql')->hasTable('comparison_profiles'));
    }

    public function test_incremental_up_down_up_restores_the_table(): void
    {
        $this->rollbackBv205();
        $path = database_path('migrations/2026_10_15_120300_bv204_create_comparison_profiles_table.php');
        $migration = require $path;
        $this->assertInstanceOf(Migration::class, $migration);

        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $this->assertFalse(Schema::connection('pgsql')->hasTable('comparison_profiles'));
        (new ReflectionMethod($migration, 'up'))->invoke($migration);
        $this->assertTrue(Schema::connection('pgsql')->hasTable('comparison_profiles'));
    }

    private function rollbackBv205(): void
    {
        $linkPath = database_path('migrations/2026_10_15_120500_bv205_add_comparison_link_to_lab_runs_table.php');
        $runsPath = database_path('migrations/2026_10_15_120400_bv205_create_comparison_runs_table.php');
        $linkMigration = require $linkPath;
        $runsMigration = require $runsPath;
        (new ReflectionMethod($linkMigration, 'down'))->invoke($linkMigration);
        (new ReflectionMethod($runsMigration, 'down'))->invoke($runsMigration);
    }
}
