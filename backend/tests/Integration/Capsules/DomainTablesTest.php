<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\PostgresTestCase;
use Tests\Support\Mdev44\DomainTables;

final class DomainTablesTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_drop_all_removes_capsule_domain_tables_on_a_fresh_migrated_database(): void
    {
        foreach (['capsules', 'capsule_versions', 'capsule_contributors', 'artifacts'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "La table {$table} doit exister après migrate:fresh.");
        }

        DomainTables::dropAll();

        foreach (['artifacts', 'capsule_contributors', 'capsule_versions', 'capsules'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "La table {$table} doit avoir été déposée.");
        }

        // Les tables parentes externes (help_requests, users) ne sont pas affectées.
        $this->assertTrue(Schema::hasTable('help_requests'));
        $this->assertTrue(Schema::hasTable('users'));
    }
}
