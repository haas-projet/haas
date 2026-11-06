<?php

declare(strict_types=1);

namespace Tests\Integration\Comparisons;

use App\Enums\Identity\AccountStatus;
use App\Enums\Lab\LabRunState;
use App\Infrastructure\Lab\ApprovedLabRegistry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\Comparisons\ComparisonFixtures;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\SpaHttpRequests;

final class StartComparisonHttpTest extends PostgresTestCase
{
    use ComparisonFixtures, RefreshDatabase, RegistryFixtures, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
        config(['lab.enabled' => true]);
    }

    public function test_nominal_accepts_and_queues_two_children(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        $this->loginAs($actor);
        $response = $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'nominal_event',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertAccepted();
        $response->assertJsonPath('data.state', 'queued');
        $response->assertJsonPath('data.profile_id', $profileId);
        $comparisonId = (string) $response->json('data.id');
        $this->assertNotEmpty($comparisonId);
        $this->assertSame(2, DB::table('lab_runs')->where('comparison_id', $comparisonId)->count());
        $sides = DB::table('lab_runs')->where('comparison_id', $comparisonId)
            ->orderBy('comparison_side')->pluck('comparison_side')->toArray();
        $this->assertSame(['baseline', 'candidate'], $sides);
        $this->assertSame('/api/v1/comparisons/'.$comparisonId, $response->headers->get('Location'));
    }

    public function test_same_key_same_payload_is_idempotent(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        $this->loginAs($actor);
        $key = (string) Str::uuid();
        $body = ['comparison_profile_id' => $profileId, 'scenario_key' => 'nominal_event'];
        $first = $this->browserRequest('POST', '/api/v1/comparisons', $body, ['Idempotency-Key' => $key]);
        $second = $this->browserRequest('POST', '/api/v1/comparisons', $body, ['Idempotency-Key' => $key]);
        $first->assertAccepted();
        $second->assertAccepted();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, DB::table('comparison_runs')->count());
        $this->assertSame(2, DB::table('lab_runs')->whereNotNull('comparison_id')->count());
        $this->assertSame(1, DB::table('api_idempotency')->count());
    }

    public function test_same_key_different_payload_is_rejected_with_409(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        $this->loginAs($actor);
        $key = (string) Str::uuid();
        $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'nominal_event',
        ], ['Idempotency-Key' => $key])->assertAccepted();
        $conflict = $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'duplicate_event',
        ], ['Idempotency-Key' => $key]);
        $conflict->assertStatus(409);
        $conflict->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame(1, DB::table('comparison_runs')->count());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function forbiddenPayloads(): iterable
    {
        yield 'code field' => [['code' => '<?php echo 1;']];
        yield 'command field' => [['command' => 'rm -rf /']];
        yield 'url field' => [['url' => 'https://evil.example']];
        yield 'path field' => [['path' => '/etc/passwd']];
        yield 'unknown field' => [['foo' => 'bar']];
    }

    /** @param array<string, mixed> $extra */
    #[DataProvider('forbiddenPayloads')]
    public function test_additional_fields_are_rejected_with_422(array $extra): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        $this->loginAs($actor);
        $response = $this->browserRequest('POST', '/api/v1/comparisons', array_merge([
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'nominal_event',
        ], $extra), ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertSame(0, DB::table('comparison_runs')->count());
    }

    public function test_missing_idempotency_key_is_422(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        $this->loginAs($actor);
        $response = $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'nominal_event',
        ]);
        $response->assertStatus(422);
        $this->assertSame(0, DB::table('comparison_runs')->count());
    }

    public function test_anonymous_is_401(): void
    {
        [$profileId] = $this->seedProfileAndActor();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'nominal_event',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertUnauthorized();
    }

    public function test_unverified_account_is_403(): void
    {
        [$profileId] = $this->seedProfileAndActor();
        $unverified = User::factory()->unverified()->create();
        $this->loginAs($unverified);
        $response = $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'nominal_event',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(403);
    }

    public function test_suspended_account_is_403(): void
    {
        [$profileId] = $this->seedProfileAndActor();
        $suspended = User::factory()->verified()->create();
        $this->loginAs($suspended);
        User::whereKey($suspended->id)->update(['status' => AccountStatus::Suspended->value]);
        $response = $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'nominal_event',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(403);
    }

    public function test_lab_switch_off_returns_503(): void
    {
        [$profileId, $actor] = $this->seedProfileAndActor();
        config(['lab.enabled' => false]);
        $this->loginAs($actor);
        $response = $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'nominal_event',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(503);
        $this->assertSame(0, DB::table('comparison_runs')->count());
    }

    public function test_unknown_profile_is_404(): void
    {
        $actor = User::factory()->verified()->create();
        $this->loginAs($actor);
        $response = $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => '00000000-0000-4000-8000-000000000001',
            'scenario_key' => 'nominal_event',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertNotFound();
    }

    public function test_disabled_profile_is_404(): void
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version, ['enabled' => false]);
        $this->definition($version);
        $actor = User::factory()->verified()->create();
        $this->loginAs($actor);
        $response = $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'nominal_event',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertNotFound();
    }

    public function test_hourly_quota_counts_two_units_per_comparison_and_returns_429(): void
    {
        [$profileId, $actor, $versionId, $definitionId] = $this->seedProfileActorAndDefinition();
        // On relâche la capacité globale pour isoler le quota horaire : seule
        // la fenêtre glissante doit refuser la 5e unité. 4 lab_runs queued du
        // même membre consomment 4 unités ; la comparaison en veut 2 ; 4+2 > 5
        // doit déclencher 429 LAB_QUOTA_EXCEEDED, pas CAPACITY_REACHED.
        config(['lab.max_running' => 100, 'lab.queue_limit' => 100, 'lab.hourly_units' => 5]);
        $this->fillHourlyWindow($actor, $versionId, $definitionId, 4);
        $this->loginAs($actor);
        $response = $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'nominal_event',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(429);
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_capacity_reached_by_another_isolated_run_blocks_the_comparison(): void
    {
        [$profileId, $actor, $versionId, $definitionId] = $this->seedProfileActorAndDefinition();
        $this->occupyCapacityWithIsolatedRun($versionId, $definitionId);
        $this->loginAs($actor);
        $response = $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'nominal_event',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(429);
        $this->assertSame(0, DB::table('comparison_runs')->count());
    }

    public function test_capacity_reached_by_another_comparison_blocks_an_isolated_run(): void
    {
        [$profileId, $actor, $versionId, $definitionId] = $this->seedProfileActorAndDefinition();
        $this->loginAs($actor);
        $accepted = $this->browserRequest('POST', '/api/v1/comparisons', [
            'comparison_profile_id' => $profileId,
            'scenario_key' => 'nominal_event',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $accepted->assertAccepted();
        // Un run simple sous le même membre doit maintenant être refusé pour
        // dépassement de capacité (1 emplacement = la comparaison existante).
        $isolated = $this->browserRequest('POST', '/api/v1/lab-runs', [
            'capsule_version_id' => $versionId,
            'scenario' => 'duplicate_event',
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $isolated->assertStatus(429);
    }

    /** @return array{0:string, 1:User, 2:string} */
    private function seedProfileAndActor(): array
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $this->definition($version);
        $actor = User::factory()->verified()->create();

        return [$profileId, $actor, $version->id];
    }

    /** @return array{0:string, 1:User, 2:string, 3:string} */
    private function seedProfileActorAndDefinition(): array
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $definitionId = $this->definition($version);
        $actor = User::factory()->verified()->create();

        return [$profileId, $actor, $version->id, $definitionId];
    }

    /**
     * Insère `count` lab_runs queued du même membre, créés dans la fenêtre
     * horaire glissante. On relâche la capacité globale au préalable
     * (max_running/queue_limit généreux) pour que ces lignes alimentent la
     * fenêtre horaire sans saturer la capacité ; le trigger B33 exige l'état
     * initial 'queued', inchangé.
     */
    private function fillHourlyWindow(User $actor, string $versionId, string $definitionId, int $count): void
    {
        $manifest = (new ApprovedLabRegistry)->manifest();
        $digest = DB::table('capsule_versions')->where('id', $versionId)->value('content_digest');
        for ($i = 0; $i < $count; $i++) {
            DB::table('lab_runs')->insert([
                'id' => (string) Str::uuid(),
                'definition_id' => $definitionId,
                'version_id' => $versionId,
                'user_id' => $actor->id,
                'request_key' => (string) Str::uuid(),
                'scenario' => 'nominal_event',
                'release' => $manifest['release'],
                'module_version' => $manifest['module_version'],
                'source_commit' => $manifest['source_commit'],
                'code_digest' => $manifest['code_digest'],
                'suite_digest' => $manifest['suite_digest'],
                'version_digest' => (string) $digest,
                'state' => LabRunState::Queued->value,
                'created_at' => CarbonImmutable::now('UTC')->subMinutes(5 + $i),
            ]);
        }
    }

    private function occupyCapacityWithIsolatedRun(string $versionId, string $definitionId): void
    {
        $manifest = (new ApprovedLabRegistry)->manifest();
        $digest = DB::table('capsule_versions')->where('id', $versionId)->value('content_digest');
        $otherUser = User::factory()->verified()->create();
        DB::table('lab_runs')->insert([
            'id' => (string) Str::uuid(),
            'definition_id' => $definitionId,
            'version_id' => $versionId,
            'user_id' => $otherUser->id,
            'request_key' => (string) Str::uuid(),
            'scenario' => 'nominal_event',
            'release' => $manifest['release'],
            'module_version' => $manifest['module_version'],
            'source_commit' => $manifest['source_commit'],
            'code_digest' => $manifest['code_digest'],
            'suite_digest' => $manifest['suite_digest'],
            'version_digest' => (string) $digest,
            'state' => LabRunState::Queued->value,
            'created_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    private function loginAs(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }
}
