<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class GeneratedApiTypesTest extends TestCase
{
    public function test_types_match_the_current_openapi_schemas(): void
    {
        $process = new Process([PHP_BINARY, base_path('../scripts/generate-api-types.php'), '--check']);
        $this->assertSame(0, $process->run(), $process->getErrorOutput());
    }
}
