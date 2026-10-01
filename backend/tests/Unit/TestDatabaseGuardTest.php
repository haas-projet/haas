<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

final class TestDatabaseGuardTest extends TestCase
{
    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_database_configuration_is_rejected(string $environment, string $driver, array $overrides): void
    {
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::check($environment, $driver, array_replace(self::safeConnection(), $overrides));
    }

    public function test_explicit_local_test_database_is_accepted(): void
    {
        TestDatabaseGuard::check('testing', 'pgsql', self::safeConnection());
        $this->expectNotToPerformAssertions();
    }

    public static function unsafeConfigurations(): array
    {
        return [
            'production environment' => ['production', 'pgsql', []],
            'sqlite fallback' => ['testing', 'sqlite', ['driver' => 'sqlite']],
            'application database' => ['testing', 'pgsql', ['database' => 'haas_app']],
            'remote server' => ['testing', 'pgsql', ['host' => 'api.haas.example.com']],
            'privileged role' => ['testing', 'pgsql', ['username' => 'postgres']],
            'database URL' => ['testing', 'pgsql', ['url' => 'pgsql://localhost/haas_app']],
            'read connection' => ['testing', 'pgsql', ['read' => ['host' => 'remote']]],
            'write connection' => ['testing', 'pgsql', ['write' => ['host' => 'remote']]],
        ];
    }

    private static function safeConnection(): array
    {
        return ['driver' => 'pgsql', 'host' => '127.0.0.1', 'database' => 'haas_bootstrap_test', 'username' => 'haas_test'];
    }
}
