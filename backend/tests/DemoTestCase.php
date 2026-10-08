<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

abstract class DemoTestCase extends TestCase
{
    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/demo/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
