<?php

namespace Tests\Support;

use RuntimeException;

final class DemoTestDatabaseGuard
{
    /** @param array<string,mixed> $connection */
    public static function check(string $environment, array $connection): void
    {
        if ($environment !== 'testing' || ($connection['driver'] ?? null) !== 'pgsql'
            || ! in_array($connection['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)
            || ! preg_match('/^haas_demo_[a-z0-9_]+_test$/D', $connection['database'] ?? '')
            || ! preg_match('/^haas_demo_[a-z0-9_]+$/D', $connection['username'] ?? '')
            || ! empty($connection['url']) || isset($connection['read']) || isset($connection['write'])
        ) {
            throw new RuntimeException('Tests B2 refusés : base PostgreSQL locale haas_demo_*_test et rôle haas_demo_* dédiés requis.');
        }
    }
}
