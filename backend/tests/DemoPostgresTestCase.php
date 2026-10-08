<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Tests\Support\DemoTestDatabaseGuard;

abstract class DemoPostgresTestCase extends DemoTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = ['demo'];

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app->make('config')->get('database.connections.demo');
        DemoTestDatabaseGuard::check($app->environment(), $connection);

        return $app;
    }
}
