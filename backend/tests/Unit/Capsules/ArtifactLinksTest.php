<?php

declare(strict_types=1);

namespace Tests\Unit\Capsules;

use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Support\Capsules\ArtifactLinks;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArtifactLinksTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function malformedOrigins(): iterable
    {
        foreach (['https://api.haas.example.com hostile', 'https://api.haas.example.com\\hostile',
            "https://api.haas.example.com\thostile", "https://api.haas.example.com\nhostile",
            'https://api.haas.example.com%0d%0ahostile'] as $origin) {
            yield bin2hex($origin) => [$origin];
        }
    }

    #[DataProvider('malformedOrigins')]
    public function test_malformed_origin_is_rejected_before_signing(string $origin): void
    {
        config(['app.url' => $origin]);
        $this->expectException(ArtifactUnavailable::class);
        (new ArtifactLinks)->signed('capsules.versions.artifact.stream', Carbon::now()->addMinutes(5), $this->parameters());
    }

    /** @return iterable<string, array{string}> */
    public static function validOrigins(): iterable
    {
        foreach (['https://api.haas.example.com', 'https://api.haas.example.com/',
            'http://localhost:8000', 'http://[::1]:8000'] as $origin) {
            yield $origin => [$origin];
        }
    }

    #[DataProvider('validOrigins')]
    public function test_valid_origin_uses_relative_signature_and_server_target(string $origin): void
    {
        config(['app.url' => $origin]);
        $link = (new ArtifactLinks)->signed('capsules.versions.artifact.stream', Carbon::now()->addMinutes(5), $this->parameters());
        $this->assertStringStartsWith(rtrim($origin, '/').'/api/v1/capsules/', $link);
        $this->assertStringContainsString('member='.$this->parameters()['member'], $link);
        $this->assertStringContainsString('signature=', $link);
    }

    /** @return array<string, string> */
    private function parameters(): array
    {
        return ['capsule' => 'f8316c23-9b26-4d15-84ee-a18dce4f2601',
            'version' => 'a064df14-3f1a-4f61-810e-dc623181dc83',
            'artifact' => '26b1aa1d-8b8a-4d23-8144-de87094d0c80',
            'member' => '70683671-b574-47e0-9b6b-e3e7b87dcb18'];
    }
}
