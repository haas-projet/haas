<?php

namespace App\Support\Demo;

use Illuminate\Foundation\Application;

final class DemoApplication extends Application
{
    public function storagePath($path = ''): string
    {
        return $this->basePath('storage'.($path === '' ? '' : DIRECTORY_SEPARATOR.$path));
    }

    public function getCachedConfigPath(): string
    {
        return $this->bootstrapPath('cache/config.php');
    }

    public function getCachedRoutesPath(): string
    {
        return $this->bootstrapPath('cache/routes-v7.php');
    }

    public function getCachedPackagesPath(): string
    {
        return $this->bootstrapPath('cache/packages.php');
    }

    public function getCachedServicesPath(): string
    {
        return $this->bootstrapPath('cache/services.php');
    }

    public function getCachedEventsPath(): string
    {
        return $this->bootstrapPath('cache/events.php');
    }
}
