<?php

declare(strict_types=1);

namespace Tests\Integration\VerificationCases;

use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\AccountStatus;
use App\Enums\VerificationCases\VerificationCaseState;
use App\Models\Capsules\CapsuleVersion;
use App\Models\HelpRequest;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class VerificationCaseHttpTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_anonymous_cannot_create_a_case(): void
    {
        $parent = $this->visibleHelpRequest();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $response->assertUnauthorized();
        $this->assertDatabaseCount('verification_cases', 0);
    }

    public function test_unverified_account_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $this->loginAs($user);
        $parent = $this->visibleHelpRequest();
        $response = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $response->assertStatus(403);
        $this->assertDatabaseCount('verification_cases', 0);
    }

    public function test_suspended_account_is_refused(): void
    {
        $user = User::factory()->verified()->create();
        $this->loginAs($user);
        User::whereKey($user->id)->update(['status' => AccountStatus::Suspended->value]);
        $parent = $this->visibleHelpRequest();
        $response = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $response->assertStatus(403);
        $this->assertDatabaseCount('verification_cases', 0);
    }

    public function test_author_creates_a_case_on_a_visible_help_request(): void
    {
        $author = User::factory()->verified()->create();
        $this->loginAs($author);
        $parent = $this->visibleHelpRequest();
        $response = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $response->assertCreated();
        $response->assertJsonPath('data.author_id', $author->id);
        $response->assertJsonPath('data.state', VerificationCaseState::Draft->value);
        $this->assertDatabaseCount('verification_cases', 1);
        $this->assertDatabaseCount('verification_case_revisions', 1);
    }

    public function test_hidden_help_request_parent_is_treated_as_not_found(): void
    {
        $author = User::factory()->verified()->create();
        $this->loginAs($author);
        $parent = $this->visibleHelpRequest(['hidden_at' => now()]);
        $response = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $response->assertNotFound();
        $this->assertDatabaseCount('verification_cases', 0);
    }

    public function test_body_rejects_executable_keys(): void
    {
        $author = User::factory()->verified()->create();
        $this->loginAs($author);
        $parent = $this->visibleHelpRequest();
        $payload = $this->validPayload($parent->id);
        $payload['body']['code'] = 'DROP TABLE users';
        $payload['body']['command'] = 'rm -rf /';
        $payload['body']['url'] = 'https://evil';
        $payload['body']['unknown'] = 'x';
        $response = $this->browserRequest('POST', '/api/v1/verification-cases', $payload, $this->keyHeader());
        $response->assertStatus(422);
        $fields = $response->json('error.fields') ?? [];
        $this->assertArrayHasKey('body.code', $fields);
        $this->assertArrayHasKey('body.command', $fields);
        $this->assertArrayHasKey('body.url', $fields);
        $this->assertArrayHasKey('body.unknown', $fields);
    }

    public function test_submit_then_patch_creates_a_new_revision_and_resets_to_draft(): void
    {
        $author = User::factory()->verified()->create();
        $this->loginAs($author);
        $parent = $this->visibleHelpRequest();
        $created = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $created->assertCreated();
        $caseId = $created->json('data.id');
        $lockV1 = (int) $created->json('data.lock_version');

        $submitted = $this->browserRequest('POST', '/api/v1/verification-cases/'.$caseId.'/submit', ['lock_version' => $lockV1], $this->keyHeader());
        $submitted->assertOk();
        $submitted->assertJsonPath('data.state', VerificationCaseState::Submitted->value);
        $submitted->assertJsonPath('data.current_revision', 1);
        $lockV2 = (int) $submitted->json('data.lock_version');

        $body = $this->validBody();
        $body['title'] = 'Vérifier la nouvelle règle après retour correctif';
        $revised = $this->browserRequest('PATCH', '/api/v1/verification-cases/'.$caseId, [
            'lock_version' => $lockV2,
            'body' => $body,
        ], $this->keyHeader());
        $revised->assertOk();
        $revised->assertJsonPath('data.state', VerificationCaseState::Draft->value);
        $revised->assertJsonPath('data.current_revision', 0);
        $this->assertSame(2, $revised->json('data.revisions.1.revision_no'));
        $this->assertNotNull($revised->json('data.revisions.0.submitted_at'));
        $this->assertNull($revised->json('data.revisions.1.submitted_at'));
    }

    public function test_patch_with_stale_lock_version_is_rejected_409(): void
    {
        $author = User::factory()->verified()->create();
        $this->loginAs($author);
        $parent = $this->visibleHelpRequest();
        $created = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $caseId = $created->json('data.id');
        $response = $this->browserRequest('PATCH', '/api/v1/verification-cases/'.$caseId, [
            'lock_version' => 42,
            'body' => $this->validBody(),
        ], $this->keyHeader());
        $response->assertStatus(409);
    }

    public function test_another_member_cannot_edit_someone_elses_draft(): void
    {
        $author = User::factory()->verified()->create();
        $other = User::factory()->verified()->create();
        $parent = $this->visibleHelpRequest();
        $case = VerificationCase::factory()->create(['author_id' => $author->id, 'help_request_id' => $parent->id]);
        $this->loginAs($other);
        $response = $this->browserRequest('PATCH', '/api/v1/verification-cases/'.$case->id, [
            'lock_version' => 1,
            'body' => $this->validBody(),
        ], $this->keyHeader());
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_withdraw_without_reason_is_422(): void
    {
        $author = User::factory()->verified()->create();
        $this->loginAs($author);
        $parent = $this->visibleHelpRequest();
        $created = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $caseId = $created->json('data.id');
        $lock = (int) $created->json('data.lock_version');
        $response = $this->browserRequest('POST', '/api/v1/verification-cases/'.$caseId.'/withdraw', [
            'lock_version' => $lock,
        ], $this->keyHeader());
        $response->assertStatus(422);
        $this->assertArrayHasKey('reason', $response->json('error.fields') ?? []);
    }

    public function test_withdraw_from_integrated_state_is_409(): void
    {
        $author = User::factory()->verified()->create();
        $parent = $this->visibleHelpRequest();
        $case = VerificationCase::factory()->create([
            'author_id' => $author->id,
            'help_request_id' => $parent->id,
            'state' => VerificationCaseState::Integrated,
            'current_revision' => 1,
        ]);
        $this->loginAs($author);
        $response = $this->browserRequest('POST', '/api/v1/verification-cases/'.$case->id.'/withdraw', [
            'lock_version' => $case->lock_version,
            'reason' => 'Motif de retrait sans valeur spéciale.',
        ], $this->keyHeader());
        $this->assertContains($response->status(), [403, 409]);
    }

    public function test_submit_replay_with_same_key_and_payload_returns_the_same_result(): void
    {
        $author = User::factory()->verified()->create();
        $this->loginAs($author);
        $parent = $this->visibleHelpRequest();
        $created = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $caseId = $created->json('data.id');
        $lock = (int) $created->json('data.lock_version');
        $key = ['Idempotency-Key' => (string) Str::uuid()];
        $first = $this->browserRequest('POST', '/api/v1/verification-cases/'.$caseId.'/submit', ['lock_version' => $lock], $key);
        $second = $this->browserRequest('POST', '/api/v1/verification-cases/'.$caseId.'/submit', ['lock_version' => $lock], $key);
        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('data.lock_version'), $second->json('data.lock_version'));
    }

    public function test_submit_replay_with_same_key_but_different_payload_is_409(): void
    {
        $author = User::factory()->verified()->create();
        $this->loginAs($author);
        $parent = $this->visibleHelpRequest();
        $created = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $caseId = $created->json('data.id');
        $lock = (int) $created->json('data.lock_version');
        $key = ['Idempotency-Key' => (string) Str::uuid()];
        $first = $this->browserRequest('POST', '/api/v1/verification-cases/'.$caseId.'/submit', ['lock_version' => $lock], $key);
        $first->assertOk();
        $second = $this->browserRequest('POST', '/api/v1/verification-cases/'.$caseId.'/submit', ['lock_version' => $lock + 42], $key);
        $second->assertStatus(409);
    }

    public function test_capsule_version_parent_is_rejected_when_not_published(): void
    {
        $author = User::factory()->verified()->create();
        $this->loginAs($author);
        $version = CapsuleVersion::factory()->create();
        $response = $this->browserRequest('POST', '/api/v1/verification-cases', [
            'parent' => ['kind' => 'capsule_version', 'help_request_id' => null, 'capsule_version_id' => $version->id],
            'body' => $this->validBody(),
        ], $this->keyHeader());
        $response->assertNotFound();
    }

    public function test_show_returns_not_found_when_help_request_parent_becomes_hidden(): void
    {
        $author = User::factory()->verified()->create();
        $this->loginAs($author);
        $parent = $this->visibleHelpRequest();
        $created = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $created->assertCreated();
        $caseId = (string) $created->json('data.id');
        HelpRequest::whereKey($parent->id)->update(['hidden_at' => now()]);
        $response = $this->browserRequest('GET', '/api/v1/verification-cases/'.$caseId, [], $this->keyHeader());
        $response->assertNotFound();
    }

    public function test_submit_returns_not_found_when_help_request_parent_becomes_hidden(): void
    {
        $author = User::factory()->verified()->create();
        $this->loginAs($author);
        $parent = $this->visibleHelpRequest();
        $created = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $created->assertCreated();
        $caseId = (string) $created->json('data.id');
        $lock = (int) $created->json('data.lock_version');
        HelpRequest::whereKey($parent->id)->update(['hidden_at' => now()]);
        $response = $this->browserRequest('POST', '/api/v1/verification-cases/'.$caseId.'/submit', ['lock_version' => $lock], $this->keyHeader());
        $response->assertNotFound();
    }

    public function test_withdraw_returns_not_found_when_help_request_parent_becomes_hidden(): void
    {
        $author = User::factory()->verified()->create();
        $this->loginAs($author);
        $parent = $this->visibleHelpRequest();
        $created = $this->browserRequest('POST', '/api/v1/verification-cases', $this->validPayload($parent->id), $this->keyHeader());
        $created->assertCreated();
        $caseId = (string) $created->json('data.id');
        $lock = (int) $created->json('data.lock_version');
        HelpRequest::whereKey($parent->id)->update(['hidden_at' => now()]);
        $response = $this->browserRequest('POST', '/api/v1/verification-cases/'.$caseId.'/withdraw', [
            'lock_version' => $lock,
            'reason' => 'Motif de retrait documentaire après masquage du parent.',
        ], $this->keyHeader());
        $response->assertNotFound();
    }

    /** @param array<string, mixed> $overrides */
    private function visibleHelpRequest(array $overrides = []): HelpRequest
    {
        $author = User::factory()->verified()->create();

        return HelpRequest::factory()->create([
            'author_id' => $author->id,
            'state' => HelpRequestState::Open,
            ...$overrides,
        ]);
    }

    /** @return array<string, mixed> */
    private function validPayload(string $helpRequestId): array
    {
        return [
            'parent' => ['kind' => 'help_request', 'help_request_id' => $helpRequestId, 'capsule_version_id' => null],
            'body' => $this->validBody(),
        ];
    }

    /** @return array<string, mixed> */
    private function validBody(): array
    {
        return [
            'title' => 'Vérifier la double soumission d\'une commande identique',
            'context' => "La commande est rejouée après une erreur réseau. Reproduire sans l'exécuter.",
            'steps' => "1. Soumettre une commande avec clé.\n2. Rejouer avec la même clé.\n3. Lire l'état du compteur.",
            'expected' => 'Le compteur avance d\'une unité au premier appel et reste stable au rejeu.',
            'observed' => null,
            'environment' => 'Documentaire uniquement.',
        ];
    }

    private function loginAs(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }

    /** @return array<string, string> */
    private function keyHeader(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }
}
