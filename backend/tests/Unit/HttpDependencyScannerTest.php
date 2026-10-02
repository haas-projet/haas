<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\HttpDependencyScanner;

final class HttpDependencyScannerTest extends TestCase
{
    #[DataProvider('httpDependencies')]
    public function test_http_dependencies_are_detected_without_executing_source(string $source): void
    {
        $this->assertNotEmpty(HttpDependencyScanner::violations('<?php '.$source));
    }

    /** @return array<string, array{string}> */
    public static function httpDependencies(): array
    {
        return [
            'aliased request' => ['namespace App\Data; use Illuminate\Http\Request as Input; readonly class Data { public function __construct(public Input $input) {} }'],
            'grouped import' => ['namespace App\Services; use Symfony\Component\HttpFoundation\{Request, Response};'],
            'fully qualified response' => ['namespace App\Services; function run() { return new \Illuminate\Http\JsonResponse; }'],
            'application request' => ['namespace App\Data; use App\Http\Requests\CreateRequest;'],
            'facade' => ['namespace App\Services; use Illuminate\Support\Facades\Auth as Actor; function run() { return Actor::user(); }'],
            'http client facade' => ['namespace App\Services; use Illuminate\Support\Facades\Http;'],
            'global helper' => ['namespace App\Services; function run() { return \response(); }'],
            'unqualified helper' => ['namespace App\Services; function run() { return auth()->user(); }'],
            'aliased helper' => ['namespace App\Services; use function request as input; function run() { return input(); }'],
        ];
    }

    public function test_domain_types_comments_and_literals_are_allowed(): void
    {
        $source = <<<'PHP'
        <?php
        namespace App\Data;
        use App\Models\User;
        // Documentation: Illuminate\Http\Request and request() are not executed here.
        readonly class Data {
            public function __construct(public User $actor, public string $name = 'response()') {}
        }
        PHP;

        $this->assertSame([], HttpDependencyScanner::violations($source));
    }
}
