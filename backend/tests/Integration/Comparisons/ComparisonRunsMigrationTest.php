<?php

declare(strict_types=1);

namespace Tests\Integration\Comparisons;

use App\Enums\Comparisons\ComparisonSide;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
use Tests\PostgresTestCase;
use Tests\Support\Comparisons\ComparisonRunFixtures;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\Mdev44\DomainTables;

final class ComparisonRunsMigrationTest extends PostgresTestCase
{
    use ComparisonRunFixtures, DatabaseMigrations, RegistryFixtures;

    protected function tearDown(): void
    {
        try {
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_fresh_migrations_create_comparison_runs_and_lab_runs_link(): void
    {
        $this->assertTrue(Schema::connection('pgsql')->hasTable('comparison_runs'));
        $this->assertTrue(Schema::connection('pgsql')->hasColumn('comparison_runs', 'profile_id'));
        $this->assertTrue(Schema::connection('pgsql')->hasColumn('lab_runs', 'comparison_id'));
        $this->assertTrue(Schema::connection('pgsql')->hasColumn('lab_runs', 'comparison_side'));
    }

    public function test_down_removes_the_table_and_columns_tolerantly(): void
    {
        $linkPath = database_path('migrations/2026_10_15_120500_bv205_add_comparison_link_to_lab_runs_table.php');
        $runsPath = database_path('migrations/2026_10_15_120400_bv205_create_comparison_runs_table.php');
        $linkMigration = require $linkPath;
        $runsMigration = require $runsPath;

        (new ReflectionMethod($linkMigration, 'down'))->invoke($linkMigration);
        $this->assertFalse(Schema::connection('pgsql')->hasColumn('lab_runs', 'comparison_id'));
        $this->assertFalse(Schema::connection('pgsql')->hasColumn('lab_runs', 'comparison_side'));

        (new ReflectionMethod($runsMigration, 'down'))->invoke($runsMigration);
        $this->assertFalse(Schema::connection('pgsql')->hasTable('comparison_runs'));

        // Rejouer les down() alors que la table ou la colonne est absente : no-op.
        (new ReflectionMethod($linkMigration, 'down'))->invoke($linkMigration);
        (new ReflectionMethod($runsMigration, 'down'))->invoke($runsMigration);
    }

    public function test_down_refuses_when_comparison_runs_remain_linked_on_lab_runs(): void
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $actor = User::factory()->verified()->create();
        $comparison = $this->pendingComparisonRun($profileId, $actor);
        $definitionId = $this->definition($version);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Baseline, $version);

        $linkPath = database_path('migrations/2026_10_15_120500_bv205_add_comparison_link_to_lab_runs_table.php');
        $linkMigration = require $linkPath;
        $this->assertInstanceOf(Migration::class, $linkMigration);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Retour BV205 refusé');
        (new ReflectionMethod($linkMigration, 'down'))->invoke($linkMigration);
    }

    public function test_incremental_up_down_up_restores_the_table_and_columns(): void
    {
        $linkPath = database_path('migrations/2026_10_15_120500_bv205_add_comparison_link_to_lab_runs_table.php');
        $runsPath = database_path('migrations/2026_10_15_120400_bv205_create_comparison_runs_table.php');
        $linkMigration = require $linkPath;
        $runsMigration = require $runsPath;

        (new ReflectionMethod($linkMigration, 'down'))->invoke($linkMigration);
        (new ReflectionMethod($runsMigration, 'down'))->invoke($runsMigration);
        $this->assertFalse(Schema::connection('pgsql')->hasTable('comparison_runs'));

        (new ReflectionMethod($runsMigration, 'up'))->invoke($runsMigration);
        (new ReflectionMethod($linkMigration, 'up'))->invoke($linkMigration);
        $this->assertTrue(Schema::connection('pgsql')->hasTable('comparison_runs'));
        $this->assertTrue(Schema::connection('pgsql')->hasColumn('lab_runs', 'comparison_id'));
    }
}
