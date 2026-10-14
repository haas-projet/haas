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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\SpaHttpRequests;
use Tests\Support\TestDatabaseGuard;

final class ReuseReportHttpTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
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

    public function test_member_records_a_reuse_report_as_human_declaration(): void
    {
        $member = User::factory()->verified()->create();
        [$capsule, $version] = $this->publishedVersion('retour-humain');
        $this->loginAs($member);
        $response = $this->browserRequest('POST', $this->reuseUrl($capsule, $version), $this->payload(), $this->keyHeader());
        $response->assertCreated();
        $data = $response->json('data');
        $this->assertSame('human_declaration', $data['kind']);
        $this->assertSame('reproduced', $data['outcome']);
        $this->assertSame(1, $data['revision']);
        $this->assertFalse($data['is_internal']);
        $this->assertArrayNotHasKey('user_id', $data);
    }

    public function test_one_active_report_per_member_version_second_post_adds_a_revision(): void
    {
        $member = User::factory()->verified()->create();
        [$capsule, $version] = $this->publishedVersion('revision-retour');
        $this->loginAs($member);
        $first = $this->browserRequest('POST', $this->reuseUrl($capsule, $version), $this->payload(), $this->keyHeader());
        $first->assertCreated();
        $reportId = $first->json('data.id');
        $second = $this->browserRequest('POST', $this->reuseUrl($capsule, $version), $this->payload(observation: 'Nouvelle observation plus détaillée que la précédente.'), $this->keyHeader());
        $second->assertOk();
        $this->assertSame($reportId, $second->json('data.id'));
        $this->assertSame(2, (int) $second->json('data.revision'));
        $this->assertSame(1, DB::table('reuse_reports')->where('user_id', $member->id)->where('version_id', $version->id)->count());
        $this->assertSame(1, DB::table('reuse_report_revisions')->where('report_id', $reportId)->count());
    }

    public function test_internal_flag_is_computed_for_owner_and_contributor(): void
    {
        [$owner, $capsule, $version] = $this->publishedVersionWithOwner('interne-retour');
        $this->loginAs($owner);
        $response = $this->browserRequest('POST', $this->reuseUrl($capsule, $version), $this->payload(), $this->keyHeader());
        $response->assertCreated();
        $this->assertTrue($response->json('data.is_internal'));
    }

    public function test_non_published_version_is_refused(): void
    {
        $member = User::factory()->verified()->create();
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => 'brouillon-retour']);
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'state' => CapsuleVersionState::Draft,
            'lock_version' => 1,
        ]);
        $this->loginAs($member);
        $response = $this->browserRequest('POST', $this->reuseUrl($capsule, $version), $this->payload(), $this->keyHeader());
        $response->assertStatus(403);
    }

    public function test_replay_with_same_key_and_payload_returns_same_report(): void
    {
        $member = User::factory()->verified()->create();
        [$capsule, $version] = $this->publishedVersion('rejeu-retour');
        $this->loginAs($member);
        $key = (string) Str::uuid();
        $first = $this->browserRequest('POST', $this->reuseUrl($capsule, $version), $this->payload(), ['Idempotency-Key' => $key]);
        $first->assertCreated();
        $second = $this->browserRequest('POST', $this->reuseUrl($capsule, $version), $this->payload(), ['Idempotency-Key' => $key]);
        $second->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, DB::table('reuse_reports')->count());
        $this->assertSame(0, DB::table('reuse_report_revisions')->count());
    }

    public function test_resource_never_contains_a_score(): void
    {
        $member = User::factory()->verified()->create();
        [$capsule, $version] = $this->publishedVersion('sans-score');
        $this->loginAs($member);
        $response = $this->browserRequest('POST', $this->reuseUrl($capsule, $version), $this->payload(), $this->keyHeader());
        $data = $response->json('data');
        foreach (['score', 'points', 'rating', 'badge'] as $banned) {
            $this->assertArrayNotHasKey($banned, $data);
        }
    }

    /** @return array{Capsule, CapsuleVersion} */
    private function publishedVersion(string $slug): array
    {
        $owner = User::factory()->verified()->create();

        return [$this->publishedCapsule($owner, $slug), $this->resolveVersion($slug)];
    }

    /** @return array{User, Capsule, CapsuleVersion} */
    private function publishedVersionWithOwner(string $slug): array
    {
        $owner = User::factory()->verified()->create();
        $capsule = $this->publishedCapsule($owner, $slug);

        return [$owner, $capsule, $this->resolveVersion($slug)];
    }

    private function publishedCapsule(User $owner, string $slug): Capsule
    {
        $moderator = User::factory()->verified()->create(['role' => Role::Moderator]);
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => $slug]);
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'body' => "## Procédure\n\nÉtape clairement décrite.",
            'limits' => 'Portée réduite.',
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
        CapsuleVersion::whereKey($version->id)->update([
            'state' => CapsuleVersionState::Published->value,
            'reviewer_id' => $moderator->id,
            'published_at' => now()->utc(),
            'content_digest' => hash('sha256', $slug.' / 1.0.0'),
            'lock_version' => 2,
        ]);

        return $capsule;
    }

    private function resolveVersion(string $slug): CapsuleVersion
    {
        /** @var CapsuleVersion $version */
        $version = CapsuleVersion::query()
            ->whereHas('capsule', fn ($query) => $query->where('slug', $slug))
            ->firstOrFail();

        return $version;
    }

    private function reuseUrl(Capsule $capsule, CapsuleVersion $version): string
    {
        return "/api/v1/capsules/{$capsule->id}/versions/{$version->id}/reuse-reports";
    }

    /** @return array<string, mixed> */
    private function payload(string $observation = 'Observation structurée et longue de plus de vingt caractères.'): array
    {
        return [
            'environment' => 'macOS 14 + PHP 8.4',
            'reported_at' => now()->format('Y-m-d'),
            'outcome' => 'reproduced',
            'observation' => $observation,
        ];
    }

    /** @return array<string, string> */
    private function keyHeader(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }

    private function loginAs(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }
}
