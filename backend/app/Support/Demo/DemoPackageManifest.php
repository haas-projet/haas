<?php

namespace App\Support\Demo;

use Illuminate\Foundation\PackageManifest;

final class DemoPackageManifest extends PackageManifest
{
    /** @return list<class-string> */
    public function providers(): array
    {
        return [];
    }

    /** @return array<string, class-string> */
    public function aliases(): array
    {
        return [];
    }
}
