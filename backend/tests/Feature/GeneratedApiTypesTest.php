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

    public function test_catalogue_types_preserve_nullable_response_values(): void
    {
        $types = file_get_contents(base_path('../docs/api/generated/haas-api.d.ts'));
        $this->assertIsString($types);
        $this->assertMatchesRegularExpression('/export type capsules_lab_CapsuleCataloguePreview = .*readonly "version": \{.*\} \| null; readonly "last_published_at":/m', $types);
        $this->assertStringContainsString('readonly "limits": string | null;', $types);
        $this->assertStringContainsString('readonly "editorial_origin"?: string | null;', $types);
    }
}
