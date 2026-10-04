<?php

namespace Tests\Integration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Tests\PostgresTestCase;

final class IdentityMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    private function runIdentityMigration(string $method): void
    {
        if ($method === 'down') {
            // Les tables des domaines consommateurs référencent `technologies` et `users` ;
            // les retirer avant le down() de B05 pour que PostgreSQL puisse déposer la table parente.
            foreach (['help_request_revisions', 'resolutions', 'comments', 'proposals', 'request_technologies', 'help_requests'] as $dependent) {
                Schema::dropIfExists($dependent);
            }
        }
        $migration = require database_path('migrations/2026_10_02_000005_b05_create_identity_and_reference_tables.php');
        $this->assertInstanceOf(Migration::class, $migration);
        (new ReflectionMethod($migration, $method))->invoke($migration);
    }

    public function test_existing_accounts_are_preserved_and_rollback_restores_legacy_shape(): void
    {
        $this->runIdentityMigration('down');
        $id = (string) Str::uuid();
        DB::table('users')->insert(['id' => $id, 'name' => ' AncienMembre ', 'email' => ' AWA@Example.test ', 'password' => 'hash-ancien']);
        $this->runIdentityMigration('up');

        $this->assertDatabaseHas('users', ['id' => $id, 'name' => ' AncienMembre ', 'handle' => 'AncienMembre', 'email' => 'awa@example.test', 'password' => 'hash-ancien', 'role' => 'member', 'status' => 'active']);
        $newId = (string) Str::uuid();
        DB::table('users')->insert(['id' => $newId, 'handle' => 'NouveauMembre', 'email' => 'nouveau@example.test', 'password' => 'hash-nouveau']);
        $this->runIdentityMigration('down');
        $this->assertFalse(Schema::hasColumn('users', 'handle'));
        $this->assertDatabaseHas('users', ['id' => $id, 'name' => ' AncienMembre ', 'password' => 'hash-ancien']);
        $this->assertDatabaseHas('users', ['id' => $newId, 'name' => 'NouveauMembre']);
    }

    #[DataProvider('invalidLegacyAccounts')]
    public function test_migration_refuses_ambiguous_legacy_data_without_rewriting_it(string $name, string $email): void
    {
        $this->runIdentityMigration('down');
        DB::table('users')->insert([
            ['id' => (string) Str::uuid(), 'name' => 'Awa', 'email' => 'awa@example.test', 'password' => 'hash-test'],
            ['id' => (string) Str::uuid(), 'name' => $name, 'email' => $email, 'password' => 'hash-test'],
        ]);
        try {
            $this->runIdentityMigration('up');
            $this->fail('Une correction explicite des données existantes est nécessaire.');
        } catch (RuntimeException $exception) {
            $this->assertStringStartsWith('Migration B05 refusée', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('users', 'handle'));
        $this->assertDatabaseHas('users', ['name' => $name, 'email' => $email]);
        $this->assertDatabaseCount('users', 2);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidLegacyAccounts(): iterable
    {
        yield 'duplicate handle' => [' awa ', 'autre@example.test'];
        yield 'duplicate email' => ['AutreMembre', ' AWA@EXAMPLE.TEST '];
        yield 'short handle' => ['ab', 'autre@example.test'];
        yield 'long handle' => [str_repeat('a', 31), 'autre@example.test'];
    }
}
