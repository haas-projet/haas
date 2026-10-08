<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ContributionRole;
use App\Enums\Identity\Role;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\PostgresTestCase;
use Tests\Support\CapsuleReviewNotificationFixtures;
use Tests\Support\SpaHttpRequests;

final class CapsuleReviewHttpTest extends PostgresTestCase
{
    use CapsuleReviewNotificationFixtures, DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_owner_submits_a_draft_for_review(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $this->loginAs($owner);
        $response = $this->reviewRequest('POST', $this->submitUrl($capsule, $version));
        $response->assertOk();
        $this->assertSame(CapsuleVersionState::InReview->value, $response->json('data.state'));
        $version->refresh();
        $this->assertSame(CapsuleVersionState::InReview, $version->state);
    }

    public function test_contributor_can_submit_for_review(): void
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
        $response = $this->reviewRequest('POST', $this->submitUrl($capsule, $version));
        $response->assertOk();
    }

    public function test_third_party_cannot_submit(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $stranger = User::factory()->verified()->create();
        $this->loginAs($stranger);
        $response = $this->reviewRequest('POST', $this->submitUrl($capsule, $version));
        $response->assertStatus(403);
    }

    public function test_published_version_cannot_be_submitted(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $version->forceFill(['state' => CapsuleVersionState::Published, 'published_at' => now()->utc(),
            'reviewer_id' => User::factory()->verified()->create(['role' => Role::Moderator])->id])->save();
        $this->loginAs($owner);
        $response = $this->reviewRequest('POST', $this->submitUrl($capsule, $version));
        $response->assertStatus(403);
    }

    public function test_body_too_short_or_no_limits_blocks_submission_422(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $version->limits = null;
        $version->save();
        $this->loginAs($owner);
        $response = $this->reviewRequest('POST', $this->submitUrl($capsule, $version));
        $response->assertStatus(422);
        $this->assertContains('limits', $response->json('error.fields.content'));
    }

    public function test_moderator_requests_changes(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft(state: CapsuleVersionState::InReview);
        $moderator = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->loginAs($moderator);
        $response = $this->reviewRequest('POST', $this->requestChangesUrl($capsule, $version), [
            'note' => 'Veuillez clarifier la procédure et compléter la section limites avant resoumission.',
        ]);
        $response->assertCreated();
        $version->refresh();
        $this->assertSame(CapsuleVersionState::ChangesRequested, $version->state);
        $this->assertSame($moderator->id, $version->reviewer_id);
    }

    public function test_regular_member_cannot_review(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft(state: CapsuleVersionState::InReview);
        $this->loginAs(User::factory()->verified()->create());
        $response = $this->reviewRequest('POST', $this->requestChangesUrl($capsule, $version), [
            'note' => 'Un simple membre ne devrait pas pouvoir relire.',
        ]);
        $response->assertStatus(403);
    }

    public function test_admin_who_is_owner_cannot_self_review(): void
    {
        $admin = User::factory()->verified()->create(['role' => Role::Admin]);
        $capsule = Capsule::factory()->create(['owner_id' => $admin->id]);
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'state' => CapsuleVersionState::InReview,
            'lock_version' => 2,
        ]);
        $this->loginAs($admin);
        $response = $this->reviewRequest('POST', $this->requestChangesUrl($capsule, $version), [
            'note' => "Même admin, l'auteur ne se relit pas (CAHIER:462).",
        ]);
        $response->assertStatus(403);
    }

    public function test_admin_who_is_contributor_cannot_self_review(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft(state: CapsuleVersionState::InReview);
        $adminContributor = User::factory()->verified()->create(['role' => Role::Admin]);
        DB::table('capsule_contributors')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => $version->id,
            'user_id' => $adminContributor->id,
            'contribution_role' => ContributionRole::Fix->value,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        $this->loginAs($adminContributor);
        $response = $this->reviewRequest('POST', $this->requestChangesUrl($capsule, $version), [
            'note' => 'Même admin, un contributeur de la version ne la relit pas.',
        ]);
        $response->assertStatus(403);
    }

    public function test_note_too_short_is_422(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft(state: CapsuleVersionState::InReview);
        $moderator = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->loginAs($moderator);
        $response = $this->reviewRequest('POST', $this->requestChangesUrl($capsule, $version), [
            'note' => 'trop court',
        ]);
        $response->assertStatus(422);
    }

    public function test_draft_version_cannot_be_reviewed(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft(); // draft
        $moderator = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->loginAs($moderator);
        $response = $this->reviewRequest('POST', $this->requestChangesUrl($capsule, $version), [
            'note' => 'Un draft doit d\'abord être soumis à la revue avant correction.',
        ]);
        $response->assertStatus(403);
    }

    public function test_refused_submission_keeps_version_and_review_history_unchanged(): void
    {
        [$owner, $capsule, $version] = $this->seedDraft();
        $this->loginAs($owner);
        // Refus d’autorisation : aucun audit ni transition ne doit être écrit.
        $stranger = User::factory()->verified()->create();
        $this->loginAs($stranger);
        $response = $this->reviewRequest('POST', $this->submitUrl($capsule, $version));
        $response->assertStatus(403);
        $version->refresh();
        $this->assertSame(CapsuleVersionState::Draft, $version->state);
        $this->assertDatabaseCount('capsule_version_reviews', 0);
    }

    public function test_anonymous_cannot_submit(): void
    {
        [, $capsule, $version] = $this->seedDraft();
        $this->reviewRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->reviewRequest('POST', $this->submitUrl($capsule, $version));
        $response->assertUnauthorized();
    }

    public function test_version_from_another_capsule_is_404_on_submit(): void
    {
        [$owner, $capsuleA, $versionA] = $this->seedDraft();
        [, $capsuleB] = $this->seedDraft(slug: 'autre-capsule-24');
        $this->loginAs($owner);
        $response = $this->reviewRequest('POST', "/api/v1/capsules/{$capsuleB->id}/versions/{$versionA->id}/submit-review");
        $response->assertStatus(404);
    }

    /** @return array{User, Capsule, CapsuleVersion} */
    private function seedDraft(string $slug = 'brouillon-revue', ?CapsuleVersionState $state = null): array
    {
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => $slug]);
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'body' => "## Procédure\n\nÉtape clairement décrite pour la revue.",
            'limits' => 'Portée réduite au scénario cité.',
            'state' => $state ?? CapsuleVersionState::Draft,
            'lock_version' => 1,
        ]);

        return [$owner, $capsule, $version];
    }

    private function submitUrl(Capsule $capsule, CapsuleVersion $version): string
    {
        return "/api/v1/capsules/{$capsule->id}/versions/{$version->id}/submit-review";
    }

    private function requestChangesUrl(Capsule $capsule, CapsuleVersion $version): string
    {
        return "/api/v1/admin/capsules/{$capsule->id}/versions/{$version->id}/request-changes";
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function reviewRequest(string $method, string $url, array $body = []): TestResponse
    {
        if ($method === 'POST' && str_contains($url, '/versions/')) {
            return $this->browserRequest($method, $url, ['lock_version' => 1, ...$body], ['Idempotency-Key' => (string) Str::uuid()]);
        }

        return $this->browserRequest($method, $url, $body);
    }

    private function loginAs(User $user): void
    {
        $this->browserCookies = [];
        $this->reviewRequest('GET', '/sanctum/csrf-cookie');
        $this->reviewRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }
}
