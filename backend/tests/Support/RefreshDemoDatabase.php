<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

trait RefreshDemoDatabase
{
    use RefreshDatabase;

    private static bool $demoMigrated = false;

    private bool $applicationMigrated = false;

    protected function beforeRefreshingDatabase(): void
    {
        $this->applicationMigrated = RefreshDatabaseState::$migrated;
        RefreshDatabaseState::$migrated = self::$demoMigrated;
    }

    protected function afterRefreshingDatabase(): void
    {
        self::$demoMigrated = RefreshDatabaseState::$migrated;
        RefreshDatabaseState::$migrated = $this->applicationMigrated;
    }
}
