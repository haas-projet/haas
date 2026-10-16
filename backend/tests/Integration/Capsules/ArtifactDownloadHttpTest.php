<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\ArtifactDistributionStatus;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Identity\AccountStatus;
use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\IssueArtifactDownloadService;
use App\Services\Capsules\StreamArtifactService;
use App\Support\Capsules\VerifiedArtifactFiles;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\PostgresTestCase;
use Tests\Support\Capsules\ArtifactKitFixture;
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
            'member' => $owner->id,
        ], absolute: false);
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
            'member' => $owner->id,
        ], absolute: false);
        $this->browserRequest('GET', $this->toRelativeUrl($signed))->assertNotFound();
    }

    public function test_expired_signed_url_is_refused(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('lien-expire');
        $expired = URL::temporarySignedRoute('capsules.versions.artifact.stream', now()->subMinutes(1), [
            'capsule' => $capsule->id,
            'version' => $version->id,
            'artifact' => $artifact->id,
            'member' => $owner->id,
        ], absolute: false);
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
            'member' => $owner->id,
        ], absolute: false);
        $tampered = $this->toRelativeUrl($signed).'extra';
        $this->loginAs($owner);
        $this->browserRequest('GET', $tampered)->assertStatus(403);
    }

    public function test_path_traversal_in_private_path_is_rejected(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('path-traversal', ['private_path' => '../etc/passwd']);
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

    public function test_corrupted_bytes_cannot_issue_a_download(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('bytes-corrompus');
        Storage::disk('local')->put('artifacts/'.$artifact->private_path, 'contenu remplace');
        $this->loginAs($owner);
        $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertNotFound();
    }

    public function test_missing_kit_cannot_issue_a_download(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('kit-manquant');
        Storage::disk('local')->delete('artifacts/'.$artifact->private_path);
        $this->loginAs($owner);
        $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertNotFound();
    }

    public function test_missing_notices_cannot_issue_a_download(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('notice-manquante');
        Storage::disk('local')->delete('artifacts/'.$artifact->notices_path);
        $this->loginAs($owner);
        $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertNotFound();
    }

    public function test_stream_rechecks_bytes_after_link_issuance(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('bytes-apres-lien');
        $this->loginAs($owner);
        $issue = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertOk();
        Storage::disk('local')->put('artifacts/'.$artifact->private_path, 'contenu remplace');
        $this->browserRequest('GET', $this->toRelativeUrl((string) $issue->json('data.download_url')))->assertNotFound();
    }

    public function test_notices_have_a_guarded_url_without_registry_path(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('notice-livree');
        $this->loginAs($owner);
        $issue = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertOk();
        $this->assertArrayNotHasKey('notices_path', $issue->json('data'));
        $this->assertNotEmpty($issue->json('data.notices_url'));
        $notice = $this->browserRequest('GET', $this->toRelativeUrl((string) $issue->json('data.notices_url')))->assertOk();
        $noticeBytes = $notice->streamedContent();
        $this->assertSame('Notice de fixture uniquement : aucun droit reel accorde.', $noticeBytes);
        $this->assertSame('nosniff', $notice->headers->get('X-Content-Type-Options'));
        $this->assertSame(hash('sha256', $noticeBytes), $issue->json('data.notices_sha256'));
    }

    public function test_verified_snapshot_is_delivered_when_source_changes_before_response_callback(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('snapshot-stable');
        $delivery = app(StreamArtifactService::class)->handle($owner, $capsule->id, $version->id, $artifact->id);
        Storage::disk('local')->put('artifacts/'.$artifact->private_path, 'remplacement apres autorisation');
        ob_start();
        $delivery->send();
        $bytes = ob_get_clean();
        $this->assertSame('ZIP factice pour snapshot-stable', $bytes);
        $this->assertSame($artifact->sha256, hash('sha256', $bytes));
        $delivery->close();
    }

    public function test_other_member_cannot_reuse_issued_link(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('lien-membre');
        $this->loginAs($owner);
        $issue = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertOk();
        $other = User::factory()->verified()->create();
        $this->loginAs($other);
        foreach (['download_url', 'notices_url'] as $field) {
            $this->browserRequest('GET', $this->toRelativeUrl((string) $issue->json('data.'.$field)))->assertNotFound();
        }
    }

    public function test_signed_notices_are_cut_after_withdrawal(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('notice-retiree');
        $this->loginAs($owner);
        $issue = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertOk();
        CapsuleVersion::whereKey($version->id)->update(['state' => CapsuleVersionState::Withdrawn->value]);
        $this->browserRequest('GET', $this->toRelativeUrl((string) $issue->json('data.notices_url')))->assertNotFound();
    }

    public function test_corrupted_notice_blocks_both_signed_downloads(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('notice-corrompue');
        $this->loginAs($owner);
        $issue = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertOk();
        Storage::disk('local')->put('artifacts/'.$artifact->notices_path, 'notice remplacee');
        foreach (['download_url', 'notices_url'] as $field) {
            $this->browserRequest('GET', $this->toRelativeUrl((string) $issue->json('data.'.$field)))->assertNotFound();
        }
    }

    public function test_service_rechecks_withdrawal_instead_of_trusting_old_model(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('etat-courant');
        CapsuleVersion::whereKey($version->id)->update(['state' => CapsuleVersionState::Withdrawn->value]);
        $this->expectException(ArtifactUnavailable::class);
        app(IssueArtifactDownloadService::class)->handle($owner, $capsule->id, $version->id, $artifact->id);
    }

    public function test_service_rechecks_actor_instead_of_trusting_old_model(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('acteur-courant');
        User::whereKey($owner->id)->update(['status' => AccountStatus::Suspended->value]);
        $this->expectException(ArtifactUnavailable::class);
        app(IssueArtifactDownloadService::class)->handle($owner, $capsule->id, $version->id, $artifact->id);
    }

    public function test_service_checks_capsule_version_binding(): void
    {
        [$owner, , $version, $artifact] = $this->seedKit('liaison-version');
        $other = Capsule::factory()->create();
        $this->expectException(ArtifactUnavailable::class);
        app(IssueArtifactDownloadService::class)->handle($owner, $other->id, $version->id, $artifact->id);
    }

    public function test_published_artifact_metadata_cannot_be_changed(): void
    {
        [, , , $artifact] = $this->seedKit('metadata-figee');
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/artifacts_published_immutable/');
        Artifact::whereKey($artifact->id)->update(['sha256' => str_repeat('a', 64)]);
    }

    public function test_registered_size_above_server_bound_is_unavailable(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('taille-bornee', ['size' => VerifiedArtifactFiles::MAX_KIT_BYTES + 1]);
        $this->loginAs($owner);
        $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertNotFound();
    }

    public function test_registry_cannot_reference_another_private_storage_namespace(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('autre-stockage', ['private_path' => 'session-fixture.txt']);
        Storage::disk('local')->put('session-fixture.txt', 'ZIP factice pour autre-stockage');
        $this->loginAs($owner);
        $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertNotFound();
    }

    public function test_shared_member_quota_covers_issue_stream_and_notices(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('quota-partage');
        $this->loginAs($owner);
        $issue = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertOk();
        for ($i = 0; $i < 29; $i++) {
            $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertOk();
        }
        foreach (['download_url', 'notices_url'] as $field) {
            $this->browserRequest('GET', $this->toRelativeUrl((string) $issue->json('data.'.$field)))->assertStatus(429);
        }
        $this->assertSame(30, DB::table('content_revisions')->where('resource_id', $version->id)
            ->where('action', 'capsule.version.artifact.download_issued')->count());
    }

    public function test_foreign_origin_cannot_issue_or_replay_a_link(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('origine-refusee');
        $this->loginAs($owner);
        $issue = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertOk();
        $headers = ['Origin' => 'https://foreign.example.invalid'];
        $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact), [], $headers)->assertForbidden();
        foreach (['download_url', 'notices_url'] as $field) {
            $this->browserRequest('GET', $this->toRelativeUrl((string) $issue->json('data.'.$field)), [], $headers)->assertForbidden();
        }
        $this->assertSame(1, DB::table('content_revisions')->where('resource_id', $version->id)->count());
    }

    public function test_issued_urls_use_configured_api_origin_instead_of_host_header(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('origine-canonique');
        $this->loginAs($owner);
        $issue = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact), [], ['Host' => 'foreign.example.invalid'])->assertOk();
        foreach (['download_url', 'notices_url'] as $field) {
            $url = (string) $issue->json('data.'.$field);
            $this->assertStringStartsWith('https://api.haas.example.com/', $url);
            $this->assertStringNotContainsString('foreign.example.invalid', $url);
            $this->assertStringContainsString('member='.$owner->id, $url);
        }
    }

    public function test_signed_links_require_current_verified_account_after_issuance(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('verification-perdue');
        $this->loginAs($owner);
        $issue = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertOk();
        User::whereKey($owner->id)->update(['email_verified_at' => null]);
        foreach (['download_url', 'notices_url'] as $field) {
            $this->browserRequest('GET', $this->toRelativeUrl((string) $issue->json('data.'.$field)))->assertForbidden();
        }
    }

    public function test_signed_links_do_not_bypass_capsule_hiding_or_distribution_revocation(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('liens-masques');
        $this->loginAs($owner);
        $issue = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertOk();
        Capsule::whereKey($capsule->id)->update(['visibility' => CapsuleVisibility::Hidden->value]);
        foreach (['download_url', 'notices_url'] as $field) {
            $this->browserRequest('GET', $this->toRelativeUrl((string) $issue->json('data.'.$field)))->assertNotFound();
        }
        Capsule::whereKey($capsule->id)->update(['visibility' => CapsuleVisibility::Visible->value]);
        Artifact::whereKey($artifact->id)->update(['distribution_status' => ArtifactDistributionStatus::Inactive->value]);
        foreach (['download_url', 'notices_url'] as $field) {
            $this->browserRequest('GET', $this->toRelativeUrl((string) $issue->json('data.'.$field)))->assertNotFound();
        }
    }

    public function test_signature_binds_the_artifact_and_notice_route(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('signature-cible');
        [, , , $other] = $this->seedKit('signature-autre');
        $this->loginAs($owner);
        $issue = $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertOk();
        $url = $this->toRelativeUrl((string) $issue->json('data.download_url'));
        $this->browserRequest('GET', str_replace($artifact->id, $other->id, $url))->assertForbidden();
        $this->browserRequest('GET', str_replace('/stream?', '/notices?', $url))->assertForbidden();
        $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact).'/notices')->assertForbidden();
    }

    public function test_already_published_documentary_kit_is_independent_of_owner_account_status(): void
    {
        [$owner, $capsule, $version, $artifact] = $this->seedKit('capsule-independante');
        $member = User::factory()->verified()->create();
        User::whereKey($owner->id)->update(['status' => AccountStatus::Suspended->value, 'email_verified_at' => null]);
        $this->loginAs($member);
        $this->browserRequest('GET', $this->issueUrl($capsule, $version, $artifact))->assertOk();
    }

    /** @param array<string, mixed> $overrides
     * @return array{User, Capsule, CapsuleVersion, Artifact}
     */
    private function seedKit(string $slug, array $overrides = []): array
    {
        return ArtifactKitFixture::create($slug, $overrides);
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
