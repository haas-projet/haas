<?php

declare(strict_types=1);

namespace Tests\Integration\Lab;

use App\Enums\Identity\AccountStatus;
use App\Infrastructure\Lab\ApprovedLabRegistry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\SpaHttpRequests;

final class StartLabRunHttpTest extends PostgresTestCase
{
    use RefreshDatabase, RegistryFixtures, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
        config(['lab.enabled' => true]);
    }

    public function test_nominal_accepts_launch_as_queued_run(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->definition($version);
        $this->loginAs($user);
        $response = $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'],
            ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertAccepted();
        $response->assertJsonPath('data.state', 'queued');
        $response->assertJsonPath('data.scenario', 'nominal_event');
        $response->assertJsonPath('data.version_id', $version->id);
        $this->assertNotNull($response->json('data.id'));
        $this->assertStringContainsString('/api/v1/lab-runs/'.$response->json('data.id'), (string) $response->json('data.follow_url'));
        $this->assertSame(1, DB::table('lab_runs')->count());
        $this->assertSame('queued', DB::table('lab_runs')->sole()->state);
    }

    public function test_missing_key_is_refused_with_422_without_write(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->definition($version);
        $this->loginAs($user);
        $response = $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event']);
        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertSame(0, DB::table('lab_runs')->count());
    }

    public function test_same_key_same_payload_is_idempotent(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->definition($version);
        $this->loginAs($user);
        $key = (string) Str::uuid();
        $body = ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'];
        $first = $this->browserRequest('POST', '/api/v1/lab-runs', $body, ['Idempotency-Key' => $key]);
        $second = $this->browserRequest('POST', '/api/v1/lab-runs', $body, ['Idempotency-Key' => $key]);
        $first->assertAccepted();
        $second->assertAccepted();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, DB::table('lab_runs')->count());
        $this->assertSame(1, DB::table('api_idempotency')->count());
    }

    public function test_same_key_different_payload_is_rejected_with_409(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->definition($version);
        $this->loginAs($user);
        $key = (string) Str::uuid();
        $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'],
            ['Idempotency-Key' => $key])->assertAccepted();
        $conflict = $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'duplicate_event'],
            ['Idempotency-Key' => $key]);
        $conflict->assertStatus(409);
        $conflict->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame(1, DB::table('lab_runs')->count());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function forbiddenPayloads(): iterable
    {
        yield 'code field' => [['code' => '<?php echo 1;']];
        yield 'command field' => [['command' => 'echo 1']];
        yield 'url field' => [['url' => 'https://evil.example']];
        yield 'runner_key field' => [['runner_key' => 'b1.idempotent.v1']];
        yield 'scenario unknown' => [['scenario' => 'unknown_scenario']];
    }

    /** @param array<string, mixed> $extra */
    #[DataProvider('forbiddenPayloads')]
    public function test_unknown_or_executable_fields_are_rejected_with_422(array $extra): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->definition($version);
        $this->loginAs($user);
        $body = array_replace(['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'], $extra);
        $response = $this->browserRequest('POST', '/api/v1/lab-runs', $body,
            ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertSame(0, DB::table('lab_runs')->count());
    }

    public function test_anonymous_is_refused_with_401(): void
    {
        $version = $this->publishedVersion();
        $this->definition($version);
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'],
            ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertUnauthorized();
        $this->assertSame(0, DB::table('lab_runs')->count());
    }

    public function test_unverified_account_is_refused_with_403(): void
    {
        $user = User::factory()->unverified()->create();
        $version = $this->publishedVersion();
        $this->definition($version);
        $this->loginAs($user);
        $response = $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'],
            ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(403);
        $this->assertSame(0, DB::table('lab_runs')->count());
    }

    public function test_suspended_account_is_refused(): void
    {
        $user = User::factory()->verified()->create();
        $this->loginAs($user);
        User::whereKey($user->id)->update(['status' => AccountStatus::Suspended->value]);
        $version = $this->publishedVersion();
        $this->definition($version);
        $response = $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'],
            ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(403);
        $this->assertSame(0, DB::table('lab_runs')->count());
    }

    public function test_switch_closed_returns_503(): void
    {
        config(['lab.enabled' => false]);
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->definition($version);
        $this->loginAs($user);
        $response = $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'],
            ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(503);
        $this->assertSame(0, DB::table('lab_runs')->count());
    }

    public function test_version_without_approved_definition_returns_503(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->loginAs($user);
        $response = $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'],
            ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(503);
        $this->assertSame(0, DB::table('lab_runs')->count());
    }

    public function test_hourly_quota_exceeded_returns_429_with_retry_after(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->definition($version);
        $this->loginAs($user);
        $now = CarbonImmutable::now('UTC');
        $definitionId = DB::table('lab_definitions')->sole()->id;
        for ($i = 0; $i < 5; $i++) {
            $this->seedQueuedRun($user->id, $version->id, $definitionId, $now->subMinutes(50 - $i * 2));
        }
        $response = $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'],
            ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(429);
        $retry = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retry);
    }

    public function test_global_capacity_full_returns_429(): void
    {
        $owner = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->definition($version);
        $definitionId = DB::table('lab_definitions')->sole()->id;
        $this->seedQueuedRun(User::factory()->verified()->create()->id, $version->id, $definitionId, CarbonImmutable::now('UTC')->subMinutes(1));
        $this->loginAs($owner);
        $response = $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'],
            ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(429);
        $this->assertSame(1, DB::table('lab_runs')->count());
    }

    public function test_show_returns_run_to_owner_only(): void
    {
        $owner = User::factory()->verified()->create();
        $other = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->definition($version);
        $this->loginAs($owner);
        $created = $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'],
            ['Idempotency-Key' => (string) Str::uuid()]);
        $created->assertAccepted();
        $runId = (string) $created->json('data.id');
        $this->browserRequest('GET', '/api/v1/lab-runs/'.$runId)->assertOk()->assertJsonPath('data.id', $runId);
        $this->browserCookies = [];
        $this->loginAs($other);
        $this->browserRequest('GET', '/api/v1/lab-runs/'.$runId)->assertNotFound();
    }

    public function test_show_rejects_additional_parameters(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->definition($version);
        $this->loginAs($user);
        $created = $this->browserRequest('POST', '/api/v1/lab-runs',
            ['capsule_version_id' => $version->id, 'scenario' => 'nominal_event'],
            ['Idempotency-Key' => (string) Str::uuid()]);
        $created->assertAccepted();
        $runId = (string) $created->json('data.id');
        $response = $this->browserRequest('GET', '/api/v1/lab-runs/'.$runId.'?code=x');
        $response->assertStatus(422);
    }

    private function loginAs(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }

    private function seedQueuedRun(string $userId, string $versionId, string $definitionId, CarbonImmutable $createdAt): string
    {
        $registry = new ApprovedLabRegistry;
        $manifest = $registry->manifest();
        $digest = DB::table('lab_definitions')->where('id', $definitionId)->sole();
        $id = (string) Str::uuid();
        DB::connection('pgsql')->table('lab_runs')->insert([
            'id' => $id, 'definition_id' => $definitionId, 'version_id' => $versionId,
            'user_id' => $userId, 'request_key' => (string) Str::uuid(),
            'scenario' => 'nominal_event', 'state' => 'queued', 'created_at' => $createdAt,
            'release' => $manifest['release'], 'module_version' => $manifest['module_version'],
            'source_commit' => $manifest['source_commit'], 'code_digest' => $digest->code_digest,
            'suite_digest' => $digest->suite_digest, 'version_digest' => $digest->version_digest,
        ]);

        return $id;
    }
}
