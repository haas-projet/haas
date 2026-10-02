<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Tests\Support\HttpDependencyScanner;

final class DomainBoundariesTest extends TestCase
{
    public function test_data_and_services_do_not_depend_on_http(): void
    {
        $root = dirname(__DIR__, 2);
        $files = (new Finder)->files()->name('*.php')->in([$root.'/app/Data', $root.'/app/Services']);
        $violations = [];

        foreach ($files as $file) {
            foreach (HttpDependencyScanner::violations($file->getContents()) as $violation) {
                $violations[] = $file->getPathname().': '.$violation;
            }
        }

        $this->assertSame([], $violations, "Les couches Data/Services doivent rester indépendantes de HTTP :\n".implode("\n", $violations));
    }
}
