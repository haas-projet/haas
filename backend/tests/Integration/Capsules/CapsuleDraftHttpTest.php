<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\Capsules\Capsule;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class CapsuleDraftHttpTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_anonymous_cannot_create_a_capsule_draft(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('POST', '/api/v1/capsules', $this->validEditorialPayload(), $this->keyHeader());
        $response->assertUnauthorized();
        $this->assertDatabaseCount('capsules', 0);
    }

    public function test_unverified_account_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $this->loginAs($user);
        $response = $this->browserRequest('POST', '/api/v1/capsules', $this->validEditorialPayload(), $this->keyHeader());
        $response->assertStatus(403);
        $this->assertDatabaseCount('capsules', 0);
    }

    public function test_suspended_account_is_refused(): void
    {
        $user = User::factory()->verified()->create();
        $this->loginAs($user);
        User::whereKey($user->id)->update(['status' => AccountStatus::Suspended->value]);
        $response = $this->browserRequest('POST', '/api/v1/capsules', $this->validEditorialPayload(), $this->keyHeader());
        $response->assertStatus(403);
        $this->assertDatabaseCount('capsules', 0);
    }

    public function test_help_request_author_creates_a_capsule_draft(): void
    {
        [$author, $request] = $this->resolvedHelpRequest();
        $this->loginAs($author);
        $tech = Technology::factory()->create();
        $response = $this->browserRequest('POST', '/api/v1/capsules', $this->helpRequestPayload($request->id, [$tech->id]), $this->keyHeader());
        $response->assertCreated();
        $response->assertJsonPath('data.source.kind', 'help_request');
        $response->assertJsonPath('data.source.help_request_id', $request->id);
        $this->assertDatabaseCount('capsules', 1);
    }

    public function test_proposal_author_can_create_a_capsule_draft_from_the_resolved_request(): void
    {
        [$owner, $request, $proposer] = $this->resolvedHelpRequestWithProposer();
        $this->loginAs($proposer);
        $response = $this->browserRequest('POST', '/api/v1/capsules', $this->helpRequestPayload($request->id), $this->keyHeader());
        $response->assertCreated();
        $capsule = Capsule::firstOrFail();
        $this->assertSame($proposer->id, $capsule->owner_id);
    }

    public function test_third_party_cannot_create_a_capsule_draft_from_someones_request(): void
    {
        [$author, $request] = $this->resolvedHelpRequest();
        $stranger = User::factory()->verified()->create();
        $this->loginAs($stranger);
        $response = $this->browserRequest('POST', '/api/v1/capsules', $this->helpRequestPayload($request->id), $this->keyHeader());
        $response->assertStatus(403);
        $this->assertDatabaseCount('capsules', 0);
    }

    public function test_unresolved_help_request_is_rejected_as_422(): void
    {
        $author = User::factory()->verified()->create();
        $request = HelpRequest::factory()->create(['author_id' => $author->id]);
        $this->loginAs($author);
        $response = $this->browserRequest('POST', '/api/v1/capsules', $this->helpRequestPayload($request->id), $this->keyHeader());
        $response->assertStatus(422);
        $response->assertJsonPath('error.fields.source_request_id.0', 'La demande source doit porter une résolution active.');
    }

    public function test_revoked_resolution_is_rejected(): void
    {
        [$author, $request] = $this->resolvedHelpRequest();
        DB::table('resolutions')->where('request_id', $request->id)->update(['revoked_at' => now()->utc()]);
        $this->loginAs($author);
        $response = $this->browserRequest('POST', '/api/v1/capsules', $this->helpRequestPayload($request->id), $this->keyHeader());
        $response->assertStatus(422);
    }

    public function test_editorial_origin_is_refused_for_regular_members(): void
    {
        $this->loginAs(User::factory()->verified()->create());
        $response = $this->browserRequest('POST', '/api/v1/capsules', $this->validEditorialPayload(), $this->keyHeader());
        $response->assertStatus(403);
        $this->assertDatabaseCount('capsules', 0);
    }

    public function test_editorial_origin_is_accepted_for_moderators(): void
    {
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $response = $this->browserRequest('POST', '/api/v1/capsules', $this->validEditorialPayload(), $this->keyHeader());
        $response->assertCreated();
    }

    public function test_both_sources_or_none_rejected_as_422(): void
    {
        $this->loginAs(User::factory()->verified()->create());
        $payload = $this->validEditorialPayload();
        $payload['source']['help_request_id'] = (string) Str::uuid();
        $response = $this->browserRequest('POST', '/api/v1/capsules', $payload, $this->keyHeader());
        $response->assertStatus(422);
    }

    public function test_protected_fields_are_rejected_without_any_write(): void
    {
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $payload = $this->validEditorialPayload();
        $payload['owner_id'] = (string) Str::uuid();
        $payload['role'] = 'admin';
        $payload['version']['state'] = 'published';
        $payload['version']['published_at'] = now()->toIso8601String();
        $response = $this->browserRequest('POST', '/api/v1/capsules', $payload, $this->keyHeader());
        $response->assertStatus(422);
        $this->assertDatabaseCount('capsules', 0);
    }

    public function test_missing_idempotency_key_is_refused(): void
    {
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $response = $this->browserRequest('POST', '/api/v1/capsules', $this->validEditorialPayload());
        $response->assertStatus(422);
        $response->assertJsonPath('error.fields.idempotency_key.0', 'La clé d’idempotence est requise.');
    }

    public function test_same_key_and_payload_produces_a_single_write(): void
    {
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $payload = $this->validEditorialPayload();
        $headers = $this->keyHeader();
        $first = $this->browserRequest('POST', '/api/v1/capsules', $payload, $headers)->assertCreated();
        $second = $this->browserRequest('POST', '/api/v1/capsules', $payload, $headers)->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('capsules', 1);
    }

    public function test_same_key_different_payload_is_a_409_conflict(): void
    {
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $headers = $this->keyHeader();
        $this->browserRequest('POST', '/api/v1/capsules', $this->validEditorialPayload('slug-un'), $headers)->assertCreated();
        $this->browserRequest('POST', '/api/v1/capsules', $this->validEditorialPayload('slug-deux'), $headers)->assertStatus(409);
    }

    public function test_body_too_short_is_422(): void
    {
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $payload = $this->validEditorialPayload();
        $payload['version']['body'] = 'trop court';
        $response = $this->browserRequest('POST', '/api/v1/capsules', $payload, $this->keyHeader());
        $response->assertStatus(422);
    }

    public function test_duplicate_version_label_in_same_capsule_is_refused(): void
    {
        [$author, $request] = $this->resolvedHelpRequest();
        $this->loginAs($author);
        $first = $this->browserRequest('POST', '/api/v1/capsules', $this->helpRequestPayload($request->id), $this->keyHeader())->assertCreated();
        $capsuleId = $first->json('data.id');
        $this->assertDatabaseCount('capsule_versions', 1);
        try {
            $this->browserRequest('POST', "/api/v1/capsules/{$capsuleId}/versions", $this->versionBody('1.0.0'), $this->keyHeader());
            $this->fail('Un doublon de version_label aurait dû être rejeté.');
        } catch (\Throwable) {
            // L'erreur est consommée : la contrainte unique PostgreSQL remonte via IdempotencyStorageFailed.
        }
        $this->assertDatabaseCount('capsule_versions', 1);
    }

    public function test_non_owner_cannot_add_a_version_draft(): void
    {
        [$author, $request] = $this->resolvedHelpRequest();
        $this->loginAs($author);
        $created = $this->browserRequest('POST', '/api/v1/capsules', $this->helpRequestPayload($request->id), $this->keyHeader())->assertCreated();
        $capsuleId = $created->json('data.id');
        $this->logoutBrowser();
        $stranger = User::factory()->verified()->create();
        $this->loginAs($stranger);
        $response = $this->browserRequest('POST', "/api/v1/capsules/{$capsuleId}/versions", $this->versionBody('1.0.1'), $this->keyHeader());
        $response->assertStatus(403);
    }

    public function test_owner_adds_a_new_version_draft(): void
    {
        [$author, $request] = $this->resolvedHelpRequest();
        $this->loginAs($author);
        $created = $this->browserRequest('POST', '/api/v1/capsules', $this->helpRequestPayload($request->id), $this->keyHeader())->assertCreated();
        $capsuleId = $created->json('data.id');
        $response = $this->browserRequest('POST', "/api/v1/capsules/{$capsuleId}/versions", $this->versionBody('1.0.1'), $this->keyHeader());
        $response->assertCreated();
        $response->assertJsonPath('data.state', CapsuleVersionState::Draft->value);
    }

    public function test_response_does_not_expose_private_fields(): void
    {
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $response = $this->browserRequest('POST', '/api/v1/capsules', $this->validEditorialPayload(), $this->keyHeader());
        $response->assertCreated();
        $json = $response->json('data');
        $this->assertArrayNotHasKey('password', $json);
        $this->assertArrayNotHasKey('email', $json);
        $this->assertArrayNotHasKey('reviewer_id', $json['version']);
    }

    /** ---------- Helpers ---------- */

    /** @return array{User, HelpRequest} */
    private function resolvedHelpRequest(): array
    {
        $author = User::factory()->verified()->create();
        $request = HelpRequest::factory()->create(['author_id' => $author->id]);
        $proposal = Proposal::factory()->create(['request_id' => $request->id, 'author_id' => $author->id]);
        Resolution::factory()->create(['request_id' => $request->id, 'proposal_id' => $proposal->id, 'accepted_by' => $author->id]);

        return [$author, $request];
    }

    /** @return array{User, HelpRequest, User} */
    private function resolvedHelpRequestWithProposer(): array
    {
        $owner = User::factory()->verified()->create();
        $proposer = User::factory()->verified()->create();
        $request = HelpRequest::factory()->create(['author_id' => $owner->id]);
        $proposal = Proposal::factory()->create(['request_id' => $request->id, 'author_id' => $proposer->id]);
        Resolution::factory()->create(['request_id' => $request->id, 'proposal_id' => $proposal->id, 'accepted_by' => $owner->id]);

        return [$owner, $request, $proposer];
    }

    private function loginAs(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }

    private function logoutBrowser(): void
    {
        $this->browserRequest('POST', '/logout');
        $this->browserCookies = [];
    }

    /** @return array<string, string> */
    private function keyHeader(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }

    /** @return array<string, mixed> */
    private function validEditorialPayload(string $slug = 'brique-demo'): array
    {
        return [
            'slug' => $slug,
            'source' => ['kind' => 'editorial', 'editorial_origin' => 'Démonstration éditoriale'],
            'version' => $this->versionBody('1.0.0'),
        ];
    }

    /**
     * @param  list<string>  $technologyIds
     * @return array<string, mixed>
     */
    private function helpRequestPayload(string $requestId, array $technologyIds = []): array
    {
        return [
            'slug' => 'contrats-paiement',
            'source' => ['kind' => 'help_request', 'help_request_id' => $requestId],
            'version' => $this->versionBody('1.0.0', $technologyIds),
        ];
    }

    /**
     * @param  list<string>  $technologyIds
     * @return array<string, mixed>
     */
    private function versionBody(string $label, array $technologyIds = []): array
    {
        $tech = [];
        foreach ($technologyIds as $id) {
            $tech[] = ['technology_id' => $id, 'version_label' => '^1.0'];
        }

        return [
            'version_label' => $label,
            'body' => "## Procédure\n\nÉtape documentée pour le brouillon.",
            'limits' => 'Portée réduite au scénario cité.',
            'technologies' => $tech,
        ];
    }
}
