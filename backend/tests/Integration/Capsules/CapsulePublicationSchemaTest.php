<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ReviewDecision;
use App\Enums\Identity\Role;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Capsules\CapsuleVersionReview;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\PostgresTestCase;
use Tests\Support\CapsulePublicationFixtures;

final class CapsulePublicationSchemaTest extends PostgresTestCase
{
    use CapsulePublicationFixtures, DatabaseMigrations;

    public function test_guard_downgrade_refuses_a_known_digest_before_replacing_immutability(): void
    {
        $version = $this->published(str_repeat('a', 64));
        $before = $this->guardDefinition();
        $migration = require base_path('database/migrations/2026_10_08_011000_b25_protect_content_digest_on_published_versions.php');
        $refused = false;
        try {
            $migration->down();
        } catch (\RuntimeException $e) {
            $refused = str_contains($e->getMessage(), 'Retour B25 refusé');
        } finally {
            $observed = $this->guardDefinition();
            $migration->up();
        }
        $this->assertTrue($refused, 'Une empreinte connue doit empêcher le remplacement de la garde.');
        $this->assertSame($before, $observed);
        $this->assertSame(str_repeat('a', 64), $version->refresh()->content_digest);
        $this->assertDigestImmutable($version);
    }

    public function test_column_downgrade_refuses_to_erase_a_known_digest(): void
    {
        $version = $this->published(str_repeat('b', 64));
        $migration = require base_path('database/migrations/2026_10_08_010000_add_content_digest_to_capsule_versions_table.php');
        $refused = false;
        try {
            $migration->down();
        } catch (\RuntimeException $e) {
            $refused = str_contains($e->getMessage(), 'Retour B25 refusé');
        } finally {
            $columnPreserved = Schema::hasColumn('capsule_versions', 'content_digest');
            $observedDigest = $columnPreserved ? DB::table('capsule_versions')->where('id', $version->id)->value('content_digest') : null;
            if (! $columnPreserved) {
                $migration->up();
            }
        }
        $this->assertTrue($refused, 'Le downgrade ne doit pas effacer une empreinte connue.');
        $this->assertTrue($columnPreserved);
        $this->assertSame(str_repeat('b', 64), $observedDigest);
        $this->assertDigestImmutable($version);
    }

    public function test_guard_downgrade_refuses_an_approval_even_with_a_legacy_null_digest(): void
    {
        $version = $this->published(null);
        $review = $this->review($version, ReviewDecision::Approved, 2);
        $before = $this->guardDefinition();
        $migration = require base_path('database/migrations/2026_10_08_011000_b25_protect_content_digest_on_published_versions.php');
        $refused = false;
        try {
            $migration->down();
        } catch (\RuntimeException $e) {
            $refused = str_contains($e->getMessage(), 'Retour B25 refusé');
        } finally {
            $observed = $this->guardDefinition();
            $migration->up();
        }
        $this->assertTrue($refused, 'La première descente doit refuser avant toute perte de garde.');
        $this->assertSame($before, $observed);
        $this->assertSame(2, $review->refresh()->reviewed_lock_version);
        $this->assertSame(ReviewDecision::Approved, $review->decision);
        $this->assertNull($version->refresh()->content_digest);
    }

    public function test_decision_downgrade_preserves_approval_and_its_attribution(): void
    {
        $version = $this->published(str_repeat('c', 64));
        $review = $this->review($version, ReviewDecision::Approved, 2);
        $migration = require base_path('database/migrations/2026_10_08_010500_allow_approved_review_decision.php');
        $refused = false;
        try {
            $migration->down();
        } catch (\RuntimeException $e) {
            $refused = str_contains($e->getMessage(), 'Retour B25 refusé');
        }
        $this->assertTrue($refused);
        $this->assertSame(ReviewDecision::Approved, $review->refresh()->decision);
        $this->assertSame(2, $review->reviewed_lock_version);
        $this->assertSame($version->reviewer_id, $review->reviewer_id);
        $this->assertNull($review->note);
        $this->assertDigestImmutable($version);
    }

    public function test_real_legacy_down_and_up_keep_content_dates_and_old_reviews_without_inventing_digest(): void
    {
        $version = $this->published(null);
        $review = $this->review($version, ReviewDecision::RequestChanges, null);
        $before = (array) DB::table('capsule_versions')->where('id', $version->id)->first();
        $guard = require base_path('database/migrations/2026_10_08_011000_b25_protect_content_digest_on_published_versions.php');
        $decision = require base_path('database/migrations/2026_10_08_010500_allow_approved_review_decision.php');
        $column = require base_path('database/migrations/2026_10_08_010000_add_content_digest_to_capsule_versions_table.php');
        $guard->down();
        $decision->down();
        $column->down();
        $this->assertFalse(Schema::hasColumn('capsule_versions', 'content_digest'));
        $this->assertSame($before['body'], DB::table('capsule_versions')->where('id', $version->id)->value('body'));
        $this->assertSame($review->id, DB::table('capsule_version_reviews')->sole()->id);
        $column->up();
        $decision->up();
        $guard->up();
        $version->refresh();
        $after = (array) DB::table('capsule_versions')->where('id', $version->id)->first();
        foreach (['body', 'limits', 'version_label', 'reviewer_id', 'published_at', 'created_at'] as $field) {
            $this->assertSame($before[$field], $after[$field]);
        }
        $this->assertNull($version->content_digest);
        $this->assertSame($review->note, $review->refresh()->note);
        $this->assertNull($review->reviewed_lock_version);
        $this->assertSame(ReviewDecision::RequestChanges, $review->decision);
    }

    private function published(?string $digest): CapsuleVersion
    {
        return CapsuleVersion::factory()->create([
            'state' => CapsuleVersionState::Published,
            'published_at' => now()->utc(),
            'reviewer_id' => User::factory()->verified()->create(['role' => Role::Moderator])->id,
            'lock_version' => 3,
            'content_digest' => $digest,
        ]);
    }

    private function review(CapsuleVersion $version, ReviewDecision $decision, ?int $snapshot): CapsuleVersionReview
    {
        $review = (new CapsuleVersionReview)->forceFill([
            'version_id' => $version->id,
            'reviewer_id' => $version->reviewer_id,
            'decision' => $decision,
            'note' => $decision === ReviewDecision::Approved ? null : 'Ancienne demande de correction conservée avec son attribution.',
            'reviewed_lock_version' => $snapshot,
            'created_at' => now()->utc(),
        ]);
        $review->save();

        return $review;
    }

    private function guardDefinition(): string
    {
        return DB::scalar("SELECT pg_get_functiondef('b22_guard_capsule_version()'::regprocedure)");
    }

    private function assertDigestImmutable(CapsuleVersion $version): void
    {
        $refused = false;
        try {
            DB::table('capsule_versions')->where('id', $version->id)->update(['content_digest' => str_repeat('d', 64)]);
        } catch (QueryException $e) {
            $refused = $e->getCode() === '23514';
        }
        $this->assertTrue($refused);
    }
}
