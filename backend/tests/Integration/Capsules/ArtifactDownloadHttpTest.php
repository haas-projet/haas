<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\ArtifactDistributionStatus;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Capsules\ContributionRole;
use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\SpaHttpRequests;
use Tests\Support\TestDatabaseGuard;

/**
 * B27 — Téléchargement contrôlé des artefacts (CAHIER_DES_CHARGES.md:956).
 * Les gates sont ré-évaluées à chaque requête, y compris sur lien signé.
 */
final class ArtifactDownloadHttpTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        try {
            TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
            foreach (['internal_notifications', 'notification_outbox'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('kind', 'capsule.review.changes_requested')->delete();
                }
            }
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_verified_member_receives_a_signed_download_url(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('kit-telechargement');
        $this->loginAs($owner);
        $response = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact));
        $response->assertOk();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $response->json('data.sha256'));
        $this->assertNotEmpty($response->json('data.download_url'));
        $this->assertStringContainsString('/stream', (string) $response->json('data.download_url'));
        $this->assertNotEmpty($response->json('data.expires_at'));
        $data = $response->json('data');
        foreach (['private_path', 'owner_id', 'reviewer_id', 'password', 'email'] as $leak) {
            $this->assertArrayNotHasKey($leak, $data);
        }
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function test_anonymous_cannot_issue_download(): void
    {
        [, $capsule, $version, $artifact] = $this->seedKit('anonyme-kit');
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact));
        $this->assertContains($response->status(), [401, 403], 'Un anonyme doit être refusé.');
    }

    public function test_unverified_member_cannot_issue_download(): void
    {
        [, $capsule, $version, $artifact] = $this->seedKit('non-verifie-kit');
        $unverified = User::factory()->unverified()->create();
        $this->loginAs($unverified);
        $response = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact));
        // Middleware `verified` → 403 côté API.
        $this->assertContains($response->status(), [403, 409]);
    }

    public function test_suspended_member_cannot_issue_download(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('suspendu-kit');
        $this->loginAs($owner);
        User::whereKey($owner->id)->update(['status' => AccountStatus::Suspended->value]);
        $response = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact));
        $this->assertContains($response->status(), [401, 403, 423]);
    }

    public function test_inactive_artifact_is_unavailable(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('inactif-kit');
        Artifact::whereKey($artifact->id)->update(['distribution_status' => ArtifactDistributionStatus::Inactive->value]);
        $this->loginAs($owner);
        $response = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact));
        $response->assertNotFound();
    }

    public function test_withdrawn_version_cuts_download_access(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('retire-kit');
        // Transition en retrait (contrainte CHECK requiert reviewer_id + published_at).
        CapsuleVersion::whereKey($version->id)->update(['state' => CapsuleVersionState::Withdrawn->value]);
        $this->loginAs($owner);
        $response = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact));
        $response->assertNotFound();
    }

    public function test_signed_link_issued_before_withdrawal_is_rejected_after_withdrawal(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('ancien-lien-retire');
        $signedUrl = URL::temporarySignedRoute('capsules.versions.artifact.stream', now()->addMinutes(5), [
            'capsule' => $capsule->id,
            'version' => $version->id,
            'artifact' => $artifact->id,
        ]);
        $signedPath = $this->toRelativeUrl($signedUrl);
        CapsuleVersion::whereKey($version->id)->update(['state' => CapsuleVersionState::Withdrawn->value]);
        $this->loginAs($owner);
        $response = $this->browserRequest('GET', $signedPath);
        $this->assertContains($response->status(), [403, 404], 'Un retrait doit couper les anciens liens signés.');
    }

    public function test_hidden_capsule_hides_both_routes(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('masquee-kit');
        Capsule::whereKey($capsule->id)->update(['visibility' => CapsuleVisibility::Hidden->value]);
        $this->loginAs($owner);
        $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertNotFound();
        $signed = URL::temporarySignedRoute('capsules.versions.artifact.stream', now()->addMinutes(5), [
            'capsule' => $capsule->id,
            'version' => $version->id,
            'artifact' => $artifact->id,
        ]);
        $this->browserRequest('GET', $this->toRelativeUrl($signed))->assertNotFound();
    }

    public function test_expired_signed_url_is_refused(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('lien-expire');
        $expired = URL::temporarySignedRoute('capsules.versions.artifact.stream', now()->subMinutes(1), [
            'capsule' => $capsule->id,
            'version' => $version->id,
            'artifact' => $artifact->id,
        ]);
        $this->loginAs($owner);
        $this->browserRequest('GET', $this->toRelativeUrl($expired))->assertStatus(403);
    }

    public function test_tampered_signed_url_is_refused(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('lien-falsifie');
        $signed = URL::temporarySignedRoute('capsules.versions.artifact.stream', now()->addMinutes(5), [
            'capsule' => $capsule->id,
            'version' => $version->id,
            'artifact' => $artifact->id,
        ]);
        $tampered = $this->toRelativeUrl($signed).'extra';
        $this->loginAs($owner);
        $this->browserRequest('GET', $tampered)->assertStatus(403);
    }

    public function test_path_traversal_in_private_path_is_rejected(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('path-traversal');
        Artifact::whereKey($artifact->id)->update(['private_path' => '../etc/passwd']);
        $this->loginAs($owner);
        $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertNotFound();
    }

    public function test_signed_stream_delivers_the_file_without_disk_path_disclosure(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('livraison-kit');
        $this->loginAs($owner);
        $issue = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact));
        $issue->assertOk();
        $downloadUrl = (string) $issue->json('data.download_url');
        $relative = $this->toRelativeUrl($downloadUrl);
        $stream = $this->browserRequest('GET', $relative);
        $stream->assertOk();
        $this->assertSame('no-store, private', $stream->headers->get('Cache-Control'));
        $content = $stream->streamedContent();
        $this->assertNotEmpty($content);
        $this->assertStringNotContainsString('/storage/app/private', (string) $stream->headers->get('Content-Disposition'));
    }

    /** @return array{User, Capsule, CapsuleVersion, Artifact} */
    private function seedKit(string $slug): array
    {
        $owner = User::factory()->verified()->create();
        $moderator = User::factory()->verified()->create(['role' => Role::Moderator]);
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => $slug]);
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'body' => "## Procédure\n\nÉtape clairement décrite pour le téléchargement.",
            'limits' => 'Portée réduite au scénario cité.',
            'state' => CapsuleVersionState::Draft,
            'lock_version' => 1,
        ]);
        DB::table('capsule_contributors')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => $version->id,
            'user_id' => $owner->id,
            'contribution_role' => ContributionRole::Diagnosis->value,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        $privatePath = 'kits/'.$slug.'.zip';
        Storage::disk('local')->put($privatePath, "ZIP factice pour {$slug}");
        $sha = hash('sha256', $privatePath);
        $artifact = Artifact::factory()->create([
            'version_id' => $version->id,
            'private_path' => $privatePath,
            'sha256' => $sha,
            'size' => strlen("ZIP factice pour {$slug}"),
            'distribution_status' => ArtifactDistributionStatus::Approved,
            'notices_path' => '/notices/'.$slug.'.md',
        ]);
        CapsuleVersion::whereKey($version->id)->update([
            'state' => CapsuleVersionState::Published->value,
            'reviewer_id' => $moderator->id,
            'published_at' => now()->utc(),
            'content_digest' => hash('sha256', $slug.' / 1.0.0'),
            'lock_version' => 2,
        ]);
        $version->refresh();

        return [$owner, $capsule, $version, $artifact];
    }

    private function issueUrl(Capsule $capsule, CapsuleVersion $version, Artifact $artifact): string
    {
        return "/api/v1/capsules/{$capsule->id}/versions/{$version->id}/artifact/{$artifact->id}";
    }

    private function toRelativeUrl(string $absolute): string
    {
        $parsed = parse_url($absolute);
        $path = $parsed['path'] ?? '/';

        return isset($parsed['query']) ? $path.'?'.$parsed['query'] : $path;
    }

    private function loginAs(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }
}
