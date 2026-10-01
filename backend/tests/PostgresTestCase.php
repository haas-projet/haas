<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Tests\Support\TestDatabaseGuard;

abstract class PostgresTestCase extends TestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $configuration = $app->make('config');
        $driver = $configuration->get('database.default');

        // Avant RefreshDatabase et toute migration : aucune connexion n'est ouverte ici.
        TestDatabaseGuard::check(
            $configuration->get('app.env'),
            $driver,
            $configuration->get('database.connections.'.$driver, []),
        );

        return $app;
    }
}
