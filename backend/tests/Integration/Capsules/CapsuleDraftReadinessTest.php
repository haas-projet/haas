<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\Role;
use App\Models\Capsules\Capsule;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class CapsuleDraftReadinessTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_active_resolution_on_draft_source_is_not_a_resolved_provenance(): void
    {
        $author = User::factory()->verified()->create();
        $request = HelpRequest::factory()->create(['author_id' => $author->id, 'state' => HelpRequestState::Draft]);
        $proposal = Proposal::factory()->create(['request_id' => $request->id]);
        Resolution::factory()->create(['request_id' => $request->id, 'proposal_id' => $proposal->id, 'accepted_by' => $author->id]);
        $this->login($author);
        $payload = $this->payload();
        $payload['source'] = ['kind' => 'help_request', 'help_request_id' => $request->id];
        $this->browserRequest('POST', '/api/v1/capsules', $payload, $this->key())->assertUnprocessable();
        $this->assertDatabaseCount('capsules', 0);
        $this->assertDatabaseCount('api_idempotency', 0);
    }

    public function test_duplicate_slug_has_a_real_conflict_response_without_partial_write(): void
    {
        $this->login(User::factory()->verified()->create(['role' => Role::Moderator]));
        $this->browserRequest('POST', '/api/v1/capsules', $this->payload(), $this->key())->assertCreated();
        $this->browserRequest('POST', '/api/v1/capsules', $this->payload(), $this->key())->assertConflict();
        $this->assertDatabaseCount('capsules', 1);
        $this->assertDatabaseCount('capsule_versions', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
    }

    public function test_unknown_fields_and_technology_ids_are_validation_errors(): void
    {
        $this->login(User::factory()->verified()->create(['role' => Role::Moderator]));
        $payload = $this->payload();
        $payload['unexpected'] = 'ignored?';
        $payload['source']['unexpected'] = 'ignored?';
        $payload['version']['unexpected'] = 'ignored?';
        $this->browserRequest('POST', '/api/v1/capsules', $payload, $this->key())->assertUnprocessable();
        $payload = $this->payload();
        $payload['version']['technologies'] = [['technology_id' => (string) Str::uuid()]];
        $this->browserRequest('POST', '/api/v1/capsules', $payload, $this->key())->assertUnprocessable();
        $tech = Technology::factory()->create();
        $payload['version']['technologies'] = [['technology_id' => $tech->id], ['technology_id' => strtoupper($tech->id)]];
        $this->browserRequest('POST', '/api/v1/capsules', $payload, $this->key())->assertUnprocessable();
        $this->assertDatabaseCount('capsules', 0);
    }

    public function test_unicode_body_limit_counts_characters_and_explicit_null_clears_limits(): void
    {
        $this->login(User::factory()->verified()->create(['role' => Role::Moderator]));
        $payload = $this->payload();
        $payload['version']['body'] = str_repeat('é', 24000);
        $created = $this->browserRequest('POST', '/api/v1/capsules', $payload, $this->key())->assertCreated();
        $capsuleId = $created->json('data.id');
        $versionId = $created->json('data.version.id');
        $this->browserRequest('PATCH', "/api/v1/capsules/{$capsuleId}/versions/{$versionId}", ['lock_version' => 1, 'limits' => null])
            ->assertOk()->assertJsonPath('data.limits', null)->assertJsonPath('data.lock_version', 2);
    }

    public function test_likely_secret_and_control_characters_are_refused(): void
    {
        $this->login(User::factory()->verified()->create(['role' => Role::Moderator]));
        $payload = $this->payload();
        // Valeur fictive : elle sert uniquement à vérifier le filtre indicatif.
        $payload['version']['body'] = 'Configuration fictive api_key=example-only-value';
        $this->browserRequest('POST', '/api/v1/capsules', $payload, $this->key())->assertUnprocessable();
        $payload['version']['body'] = "Documentation assez longue avec un contrôle\0interdit.";
        $this->browserRequest('POST', '/api/v1/capsules', $payload, $this->key())->assertUnprocessable();
        $this->assertDatabaseCount('capsules', 0);
    }

    public function test_replay_after_edit_is_a_conflict_and_never_projects_a_different_version(): void
    {
        $this->login(User::factory()->verified()->create(['role' => Role::Moderator]));
        $key = $this->key();
        $created = $this->browserRequest('POST', '/api/v1/capsules', $this->payload(), $key)->assertCreated();
        $capsuleId = $created->json('data.id');
        $versionId = $created->json('data.version.id');
        $this->browserRequest('PATCH', "/api/v1/capsules/{$capsuleId}/versions/{$versionId}", ['lock_version' => 1, 'limits' => 'Limites modifiées après création.'])->assertOk();
        $this->browserRequest('POST', '/api/v1/capsules', $this->payload(), $key)->assertConflict();
        $this->assertDatabaseCount('capsule_versions', 1);
    }

    public function test_second_version_replay_checks_the_current_owner(): void
    {
        $actor = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->login($actor);
        $created = $this->browserRequest('POST', '/api/v1/capsules', $this->payload(), $this->key())->assertCreated();
        $capsuleId = $created->json('data.id');
        $payload = $this->payload()['version'];
        $payload['version_label'] = '1.0.1';
        $key = $this->key();
        $this->browserRequest('POST', "/api/v1/capsules/{$capsuleId}/versions", $payload, $key)->assertCreated();
        Capsule::whereKey($capsuleId)->update(['owner_id' => User::factory()->verified()->create()->id]);
        $this->browserRequest('POST', "/api/v1/capsules/{$capsuleId}/versions", $payload, $key)->assertForbidden();
        $this->assertDatabaseCount('capsule_versions', 2);
    }

    private function login(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }

    /** @return array<string, string> */
    private function key(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }

    /** @return array{slug: string, source: array<string, string>, version: array<string, mixed>} */
    private function payload(): array
    {
        return ['slug' => 'brouillon-verifie', 'source' => ['kind' => 'editorial', 'editorial_origin' => 'Exemple documentaire'], 'version' => [
            'version_label' => '1.0.0', 'body' => 'Diagnostic et procédure documentés pour ce scénario.', 'limits' => 'Limites déclarées de la procédure.', 'technologies' => [],
        ]];
    }
}
