<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class ApiInventoryTest extends TestCase
{
    public function test_each_delivered_api_operation_has_one_documented_operation_and_no_phantom_route(): void
    {
        $root = base_path('../docs/OPENAPI.yaml');
        $contract = Yaml::parseFile($root);
        $expected = [];
        $operationIds = [];
        foreach ($contract['paths'] as $path => $item) {
            $item = $this->resolve($item, $root);
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (isset($item[$method])) {
                    $this->assertIsString($item[$method]['operationId']);
                    $this->assertNotContains($item[$method]['operationId'], $operationIds);
                    $operationIds[] = $item[$method]['operationId'];
                    $expected[] = strtoupper($method).' '.$path;
                }
            }
        }
        $actual = [];
        $auth = ['up', 'login', 'logout', 'register', 'forgot-password', 'reset-password'];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'api/v1/') || str_starts_with($uri, 'email/') || str_starts_with($uri, 'sanctum/') || in_array($uri, $auth, true)) {
                foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                    $actual[] = $method.' /'.$uri;
                }
            }
        }
        $this->assertEqualsCanonicalizing($expected, $actual, 'Chaque nouvelle route doit être décrite, chaque opération décrite doit exister.');
    }

    /** @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function resolve(array $node, string $path): array
    {
        if (! isset($node['$ref'])) {
            return $node;
        }
        [$file, $pointer] = explode('#', $node['$ref'], 2);
        $target = $file === '' ? $path : dirname($path).'/'.$file;
        $node = Yaml::parseFile($target);
        foreach (explode('/', ltrim($pointer, '/')) as $part) {
            $node = $node[str_replace(['~1', '~0'], ['/', '~'], $part)];
        }

        return $this->resolve($node, $target);
    }
}
