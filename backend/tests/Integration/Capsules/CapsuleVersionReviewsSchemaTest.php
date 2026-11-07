<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ReviewDecision;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Capsules\CapsuleVersionReview;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\CapsuleReviewNotificationFixtures;

final class CapsuleVersionReviewsSchemaTest extends PostgresTestCase
{
    use CapsuleReviewNotificationFixtures, DatabaseMigrations;

    public function test_review_is_persisted(): void
    {
        $review = CapsuleVersionReview::factory()->create();
        $this->assertDatabaseHas('capsule_version_reviews', [
            'id' => $review->id,
            'decision' => 'request_changes',
        ]);
    }

    public function test_note_too_short_is_rejected(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_version_reviews_note_min/i');
        DB::table('capsule_version_reviews')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => CapsuleVersion::factory()->create()->id,
            'reviewer_id' => User::factory()->verified()->create()->id,
            'decision' => ReviewDecision::RequestChanges->value,
            'note' => 'trop',
            'created_at' => now()->utc(),
        ]);
    }

    public function test_version_deletion_preserves_the_review_history(): void
    {
        $review = CapsuleVersionReview::factory()->create();
        $version = CapsuleVersion::findOrFail($review->version_id);
        $this->expectException(QueryException::class);
        $version->delete();
    }

    public function test_reviewer_deletion_is_restricted(): void
    {
        $review = CapsuleVersionReview::factory()->create();
        $reviewer = User::findOrFail($review->reviewer_id);
        $this->expectException(QueryException::class);
        $reviewer->delete();
    }

    public function test_review_note_cannot_be_rewritten(): void
    {
        $review = CapsuleVersionReview::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_review_history_immutable/i');
        DB::table('capsule_version_reviews')->where('id', $review->id)->update(['note' => 'Note altérée après la revue indépendante.']);
    }

    public function test_review_record_cannot_be_deleted(): void
    {
        $review = CapsuleVersionReview::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_review_history_immutable/i');
        DB::table('capsule_version_reviews')->where('id', $review->id)->delete();
    }

    public function test_additive_upgrade_and_rollback_keep_existing_notes_and_attribution(): void
    {
        $migration = require base_path('database/migrations/2026_10_07_195000_b24_preserve_review_history.php');
        $migration->down();
        $version = CapsuleVersion::factory()->create();
        $reviewer = User::factory()->verified()->create();
        $id = (string) Str::uuid();
        $note = 'Note historique conservée sans révision reconstituée.';
        DB::table('capsule_version_reviews')->insert([
            'id' => $id, 'version_id' => $version->id, 'reviewer_id' => $reviewer->id,
            'decision' => ReviewDecision::RequestChanges->value, 'note' => $note, 'created_at' => now()->utc(),
        ]);
        $migration->up();
        $this->assertDatabaseHas('capsule_version_reviews', ['id' => $id, 'version_id' => $version->id, 'reviewer_id' => $reviewer->id, 'note' => $note, 'reviewed_lock_version' => null]);
        $migration->down();
        $this->assertDatabaseHas('capsule_version_reviews', ['id' => $id, 'version_id' => $version->id, 'reviewer_id' => $reviewer->id, 'note' => $note]);
        $migration->up();
        $this->assertDatabaseCount('capsule_version_reviews', 1);
    }

    public function test_known_reviewed_version_must_be_positive(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_reviews_positive_version/i');
        CapsuleVersionReview::factory()->create(['reviewed_lock_version' => 0]);
    }

    public function test_rollback_refuses_to_erase_a_real_reviewed_version(): void
    {
        $version = CapsuleVersion::factory()->create(['state' => CapsuleVersionState::InReview, 'lock_version' => 3]);
        $review = CapsuleVersionReview::factory()->create(['version_id' => $version->id, 'reviewed_lock_version' => 3]);
        $migration = require base_path('database/migrations/2026_10_07_195000_b24_preserve_review_history.php');
        $failure = null;
        try {
            $migration->down();
        } catch (\RuntimeException $error) {
            $failure = $error;
        } finally {
            if (! Schema::hasColumn('capsule_version_reviews', 'reviewed_lock_version')) {
                $migration->up();
            }
        }
        $this->assertInstanceOf(\RuntimeException::class, $failure, 'Le downgrade ne doit pas effacer la version réellement relue.');
        $this->assertStringContainsString('Rollback B24 refusé', $failure->getMessage());
        $this->assertDatabaseHas('capsule_version_reviews', ['id' => $review->id, 'reviewed_lock_version' => 3,
            'version_id' => $review->version_id, 'reviewer_id' => $review->reviewer_id, 'note' => $review->note]);
    }
}
