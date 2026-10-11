<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Identity\Role;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class CapsuleDraftInputReadinessTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
        $actor = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $actor->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }

    /** @return iterable<string, array{string}> */
    public static function operations(): iterable
    {
        foreach (['capsule', 'version', 'update'] as $operation) {
            yield $operation => [$operation];
        }
    }

    #[DataProvider('operations')]
    public function test_body_requires_twenty_useful_characters_without_a_server_error(string $operation): void
    {
        $body = 'x'.str_repeat(' ', 19);
        $this->sendBody($operation, $body)->assertUnprocessable();
    }

    #[DataProvider('operations')]
    public function test_valid_body_preserves_its_formatting_exactly(string $operation): void
    {
        $body = "  Diagnostic documenté pour un exemple fictif.\n\n ";
        $response = $this->sendBody($operation, $body);
        $operation === 'update' ? $response->assertOk() : $response->assertCreated();
        $response->assertJsonPath($operation === 'capsule' ? 'data.version.body' : 'data.body', $body);
        $this->assertDatabaseHas('capsule_versions', ['body' => $body]);
    }

    public function test_lock_version_numeric_string_is_a_validation_error(): void
    {
        [$capsule, $version] = $this->draft();
        $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", [
            'lock_version' => '1', 'limits' => 'Limites explicitement corrigées.',
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['lock_version']]]);
        $this->assertSame(1, $version->refresh()->lock_version);
    }

    public function test_whitespace_only_body_is_not_a_no_op_update(): void
    {
        $this->sendBody('update', str_repeat(' ', 20))->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['body']]]);
    }

    /** @return TestResponse<Response> */
    private function sendBody(string $operation, string $body): TestResponse
    {
        $key = ['Idempotency-Key' => (string) Str::uuid()];
        $versionData = ['version_label' => '1.0.1', 'body' => $body, 'limits' => 'Limites déclarées du scénario.'];
        if ($operation === 'capsule') {
            return $this->browserRequest('POST', '/api/v1/capsules', [
                'slug' => 'format-documentaire', 'source' => ['kind' => 'editorial', 'editorial_origin' => 'Exemple documentaire'],
                'version' => $versionData,
            ], $key);
        }
        [$capsule, $version] = $this->draft();
        if ($operation === 'version') {
            return $this->browserRequest('POST', "/api/v1/capsules/{$capsule->id}/versions", $versionData, $key);
        }

        return $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", ['lock_version' => 1, 'body' => $body]);
    }

    /** @return array{Capsule, CapsuleVersion} */
    private function draft(): array
    {
        $capsule = Capsule::factory()->create(['owner_id' => User::where('role', Role::Moderator)->sole()->id]);

        return [$capsule, CapsuleVersion::factory()->create(['capsule_id' => $capsule->id])];
    }
}
