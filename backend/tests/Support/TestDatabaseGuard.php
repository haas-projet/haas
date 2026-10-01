<?php

namespace Tests\Support;

use RuntimeException;

final class TestDatabaseGuard
{
    /** @param array<string, mixed> $connection */
    public static function check(string $environment, string $driver, array $connection): void
    {
        if ($environment !== 'testing'
            || $driver !== 'pgsql'
            || ($connection['driver'] ?? null) !== 'pgsql'
            || ! in_array($connection['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)
            || ! preg_match('/^haas_[a-z0-9_]+_test$/D', $connection['database'] ?? '')
            || ($connection['username'] ?? null) !== 'haas_test'
            || ! empty($connection['url'])
            || isset($connection['read'])
            || isset($connection['write'])
        ) {
            throw new RuntimeException('Tests SQL refusés : utiliser une base PostgreSQL locale dédiée haas_*_test et le rôle haas_test.');
        }
    }
}
