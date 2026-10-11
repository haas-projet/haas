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

    public function test_catalogue_details_describe_the_data_response_envelope(): void
    {
        $contract = Yaml::parseFile(base_path('../docs/api/openapi/capsules-lab.yaml'));
        foreach (['/api/v1/capsules/{slug}', '/api/v1/capsules/{slug}/versions/{version}'] as $path) {
            $this->assertSame('#/components/schemas/CapsuleCatalogueDetailResponse', $contract['paths'][$path]['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        }
        $wrapper = $contract['components']['schemas']['CapsuleCatalogueDetailResponse'];
        $this->assertSame(['data'], $wrapper['required']);
        $this->assertSame('#/components/schemas/CapsuleCatalogueDetail', $wrapper['properties']['data']['$ref']);
    }

    public function test_catalogue_pagination_matches_the_shared_limits_and_defaults(): void
    {
        $contract = Yaml::parseFile(base_path('../docs/api/openapi/capsules-lab.yaml'));
        $parameters = array_column($contract['paths']['/api/v1/capsules']['get']['parameters'], 'schema', 'name');
        $this->assertSame(PageData::MAX_PAGE, $parameters['page']['maximum']);
        $this->assertSame(1, $parameters['page']['default']);
        $this->assertSame(PageData::MAX_PER_PAGE, $parameters['per_page']['maximum']);
        $this->assertSame(PageData::DEFAULT_PER_PAGE, $parameters['per_page']['default']);
    }

    public function test_catalogue_nullable_values_use_openapi_31_type_unions(): void
    {
        $contract = Yaml::parseFile(base_path('../docs/api/openapi/capsules-lab.yaml'));
        $schemas = $contract['components']['schemas'];
        $this->assertSame(['object', 'null'], $schemas['CapsuleCataloguePreview']['properties']['version']['type']);
        $this->assertSame(['string', 'null'], $schemas['CapsuleCataloguePreview']['properties']['source']['properties']['editorial_origin']['type']);
        $version = $schemas['CapsuleCatalogueDetail']['properties']['version']['properties'];
        $this->assertSame(['string', 'null'], $version['limits']['type']);
        $this->assertSame(['string', 'null'], $version['contributors']['items']['properties']['handle']['type']);
        $this->assertSame(['integer', 'null'], $schemas['PaginationMeta']['properties']['from']['type']);
        $this->assertSame(['integer', 'null'], $schemas['PaginationMeta']['properties']['to']['type']);
    }

    public function test_catalogue_content_digest_preserves_legacy_null_without_backfill(): void
    {
        $contract = Yaml::parseFile(base_path('../docs/api/openapi/capsules-lab.yaml'));
        $digest = $contract['components']['schemas']['CapsuleCatalogueDetail']['properties']['version']['properties']['content_digest'];

        $this->assertSame(['string', 'null'], $digest['type']);
        $this->assertSame('^[0-9a-f]{64}$', $digest['pattern']);
        $this->assertStringContainsString('B25', $digest['description']);
        $this->assertStringContainsString('sans recalcul', $digest['description']);
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
