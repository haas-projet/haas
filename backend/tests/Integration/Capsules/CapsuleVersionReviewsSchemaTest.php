<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\ReviewDecision;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Capsules\CapsuleVersionReview;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class CapsuleVersionReviewsSchemaTest extends PostgresTestCase
{
    use DatabaseMigrations;

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

    public function test_version_deletion_cascades_to_reviews(): void
    {
        $review = CapsuleVersionReview::factory()->create();
        $version = CapsuleVersion::findOrFail($review->version_id);
        $version->delete();
        $this->assertDatabaseMissing('capsule_version_reviews', ['id' => $review->id]);
    }

    public function test_reviewer_deletion_is_restricted(): void
    {
        $review = CapsuleVersionReview::factory()->create();
        $reviewer = User::findOrFail($review->reviewer_id);
        $this->expectException(QueryException::class);
        $reviewer->delete();
    }
}
