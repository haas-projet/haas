<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ContributionRole;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class CapsuleDraftUpdateHttpTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_owner_can_update_a_draft_and_lock_version_increments(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $this->loginAs($owner);
        $response = $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", [
            'lock_version' => 1,
            'body' => "## Procédure révisée\n\nÉtape documentée mise à jour.",
        ]);
        $response->assertOk();
        $this->assertSame(2, $response->json('data.lock_version'));
        $version->refresh();
        $this->assertSame(2, $version->lock_version);
    }

    public function test_contributor_of_this_version_can_update_it(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $contributor = User::factory()->verified()->create();
        DB::table('capsule_contributors')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => $version->id,
            'user_id' => $contributor->id,
            'contribution_role' => ContributionRole::Fix->value,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        $this->loginAs($contributor);
        $response = $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", [
            'lock_version' => 1,
            'limits' => 'Portée élargie après revue informelle.',
        ]);
        $response->assertOk();
    }

    public function test_third_party_cannot_update_a_draft(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $stranger = User::factory()->verified()->create();
        $this->loginAs($stranger);
        $response = $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", [
            'lock_version' => 1,
            'body' => "## Tentative malveillante\n\nIci on tente d'altérer le brouillon d'autrui.",
        ]);
        $response->assertStatus(403);
        $version->refresh();
        $this->assertSame(1, $version->lock_version);
    }

    public function test_protected_fields_are_rejected(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $this->loginAs($owner);
        $response = $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", [
            'lock_version' => 1,
            'state' => 'published',
            'owner_id' => (string) Str::uuid(),
            'version_label' => '9.9.9',
            'body' => "## Procédure OK\n\nContenu valide pour tester le filtrage.",
        ]);
        $response->assertStatus(422);
        $version->refresh();
        $this->assertSame(1, $version->lock_version);
    }

    public function test_published_version_cannot_be_edited(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $version->state = CapsuleVersionState::Published;
        $version->published_at = now()->utc();
        $version->reviewer_id = User::factory()->verified()->create(['role' => 'moderator'])->id;
        $version->save();
        $this->loginAs($owner);
        $response = $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", [
            'lock_version' => 1,
            'body' => "## Édition interdite\n\nUne version publiée ne doit pas être éditée.",
        ]);
        $response->assertStatus(403);
    }

    public function test_changes_requested_version_is_editable(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $version->state = CapsuleVersionState::ChangesRequested;
        $version->save();
        $this->loginAs($owner);
        $response = $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", [
            'lock_version' => 1,
            'body' => "## Correction apportée\n\nSuite aux retours de la revue.",
        ]);
        $response->assertOk();
    }

    public function test_stale_lock_version_yields_409_without_mutation(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $this->loginAs($owner);
        $response = $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", [
            'lock_version' => 42,
            'body' => "## Tentative avec version périmée\n\nNe devrait rien écrire.",
        ]);
        $response->assertStatus(409);
        $version->refresh();
        $this->assertSame(1, $version->lock_version);
        $this->assertSame('## Procédure
Original.', $version->body);
    }

    public function test_version_from_another_capsule_yields_404(): void
    {
        [$owner, $capsuleA, $versionA] = $this->seedDraft();
        [, $capsuleB] = $this->seedDraft(slug: 'autre-capsule');
        $this->loginAs($owner);
        $response = $this->browserRequest('PATCH', "/api/v1/capsules/{$capsuleB->id}/versions/{$versionA->id}", [
            'lock_version' => 1,
            'body' => "## Mauvaise capsule\n\nL'URL ne correspond pas.",
        ]);
        $response->assertStatus(404);
    }

    public function test_empty_payload_is_422(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $this->loginAs($owner);
        $response = $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", [
            'lock_version' => 1,
        ]);
        $response->assertStatus(422);
    }

    public function test_technologies_sync_replaces_the_set(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $techA = Technology::factory()->create();
        $techB = Technology::factory()->create();
        $version->technologies()->attach($techA, ['version_label' => '^1.0']);
        $this->loginAs($owner);
        $response = $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", [
            'lock_version' => 1,
            'technologies' => [
                ['technology_id' => $techB->id, 'version_label' => '^2.0'],
            ],
        ]);
        $response->assertOk();
        $this->assertDatabaseMissing('capsule_version_technologies', [
            'version_id' => $version->id,
            'technology_id' => $techA->id,
        ]);
        $this->assertDatabaseHas('capsule_version_technologies', [
            'version_id' => $version->id,
            'technology_id' => $techB->id,
            'version_label' => '^2.0',
        ]);
    }

    public function test_anonymous_cannot_update(): void
    {
        [, $capsule, $version] = $this->seedDraft();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", [
            'lock_version' => 1,
            'body' => "## Anonyme\n\nTentative sans session.",
        ]);
        $response->assertUnauthorized();
    }

    /** @return array{User, Capsule, CapsuleVersion} */
    private function seedDraft(string $slug = 'brouillon-test'): array
    {
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create([
            'owner_id' => $owner->id,
            'slug' => $slug,
        ]);
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'body' => "## Procédure\nOriginal.",
            'lock_version' => 1,
        ]);

        return [$owner, $capsule, $version];
    }

    private function loginAs(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }
}
