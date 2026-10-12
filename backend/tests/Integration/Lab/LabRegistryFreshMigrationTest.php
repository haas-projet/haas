<?php

declare(strict_types=1);

namespace Tests\Integration\Lab;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\PostgresTestCase;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\Mdev44\DomainTables;

final class LabRegistryFreshMigrationTest extends PostgresTestCase
{
    use DatabaseMigrations, RegistryFixtures;

    protected function tearDown(): void
    {
        try {
            // Guarded destruction of test fixtures, not a production downgrade.
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_repeated_fresh_migrations_preserve_release_guard_functions(): void
    {
        for ($cycle = 0; $cycle < 2; $cycle++) {
            $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
            $id = $this->definition($this->publishedVersion());
            try {
                DB::table('lab_definitions')->where('id', $id)->update(['release' => 'untrusted-release']);
                $this->fail('La protection doit survivre à migrate:fresh.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0] ?? null);
            }
            $this->assertSame('b1-source-20261011', DB::table('lab_definitions')->where('id', $id)->value('release'));
        }
    }

    public function test_empty_registry_can_be_rolled_back_and_recreated(): void
    {
        $migration = require database_path('migrations/2026_10_11_230000_b33_lab_control_registry.php');
        $migration->down();
        foreach (['lab_definitions', 'lab_runs', 'lab_results'] as $table) {
            $this->assertFalse(Schema::connection('pgsql')->hasTable($table));
        }
        $migration->up();
        foreach (['lab_definitions', 'lab_runs', 'lab_results'] as $table) {
            $this->assertTrue(Schema::connection('pgsql')->hasTable($table));
        }
    }
}
