<?php

namespace Tests\Feature;

use App\Data\Common\PageData;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class OpenApiContractTest extends TestCase
{
    public function test_common_error_matches_the_published_properties(): void
    {
        $contract = Yaml::parseFile(base_path('../docs/OPENAPI.yaml'));
        $schema = $contract['components']['schemas']['ApiError'];
        $response = $this->get('/api/v1/missing')->assertNotFound();
        $body = $response->json();

        $this->assertEqualsCanonicalizing($schema['required'], array_keys($body));
        $this->assertEqualsCanonicalizing($schema['properties']['error']['required'], array_keys($body['error']));
        $this->assertMatchesRegularExpression('/'.$schema['properties']['error']['properties']['code']['pattern'].'/', $body['error']['code']);
        $this->assertIsString($body['error']['message']);
        $this->assertSame('object', $schema['properties']['error']['properties']['fields']['type']);
        $this->assertSame('uuid', $schema['properties']['request_id']['format']);
        $response->assertHeader('X-Request-ID', $body['request_id']);
    }

    public function test_pagination_limits_match_openapi(): void
    {
        $contract = Yaml::parseFile(base_path('../docs/OPENAPI.yaml'));
        $parameters = $contract['components']['parameters'];

        $this->assertSame(1, $parameters['Page']['schema']['default']);
        $this->assertSame(PageData::MAX_PAGE, $parameters['Page']['schema']['maximum']);
        $this->assertSame(PageData::DEFAULT_PER_PAGE, $parameters['PerPage']['schema']['default']);
        $this->assertSame(PageData::MAX_PER_PAGE, $parameters['PerPage']['schema']['maximum']);
        $this->assertSame(PageData::MAX_PER_PAGE, $contract['components']['schemas']['PaginationMeta']['properties']['per_page']['maximum']);
    }

    public function test_all_local_references_resolve(): void
    {
        $path = base_path('../docs/OPENAPI.yaml');
        $contract = Yaml::parseFile($path);
        $this->assertSame('3.1.0', $contract['openapi']);
        $this->assertArrayHasKey('/up', $contract['paths']);
        $visited = [];
        $this->checkReferences($contract, $path, $visited);
        $this->assertGreaterThanOrEqual(5, count($visited));
    }

    /** @param array<string, bool> $visited */
    private function checkReferences(mixed $node, string $path, array &$visited): void
    {
        if (! is_array($node)) {
            return;
        }
        if (isset($node['$ref'])) {
            $ref = $node['$ref'];
            $this->assertIsString($ref);
            $this->assertStringNotContainsString('://', $ref);
            [$file, $pointer] = array_pad(explode('#', $ref, 2), 2, '');
            $targetPath = $file === '' ? $path : dirname($path).'/'.$file;
            $this->assertFileExists($targetPath);
            $key = realpath($targetPath).'#'.$pointer;
            if (! isset($visited[$key])) {
                $visited[$key] = true;
                $target = Yaml::parseFile($targetPath);
                foreach ($pointer === '' ? [] : explode('/', ltrim($pointer, '/')) as $segment) {
                    $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
                    $this->assertIsArray($target);
                    $this->assertArrayHasKey($segment, $target);
                    $target = $target[$segment];
                }
                $this->checkReferences($target, $targetPath, $visited);
            }
        }
        foreach ($node as $value) {
            $this->checkReferences($value, $path, $visited);
        }
    }
}
