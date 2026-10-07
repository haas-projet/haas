<?php

namespace Tests\Integration\Collaboration;

use App\Models\Comment;
use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class CommentRevisionsMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_migration_can_be_reversed_and_reapplied_without_events(): void
    {
        $migration = require database_path('migrations/2026_10_04_000017_b17_comment_revisions_and_events.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('comment_revisions'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('comment_revisions'));
    }

    public function test_rollback_refuses_to_discard_comment_notification_events(): void
    {
        $recipient = User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($recipient, 'author')->create();
        $comment = Comment::factory()->create(['request_id' => $parent->id]);
        $eventId = (string) Str::uuid();
        DB::table('notification_outbox')->insert(['id' => $eventId, 'event_id' => $comment->id, 'recipient_id' => $recipient->id, 'kind' => 'comment.created', 'created_at' => now()]);
        $migration = require database_path('migrations/2026_10_04_000017_b17_comment_revisions_and_events.php');
        try {
            $migration->down();
            $this->fail('Le retour B17 doit conserver les événements existants.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('événements de commentaires à conserver', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('comment_revisions'));
        $this->assertDatabaseHas('notification_outbox', ['id' => $eventId, 'kind' => 'comment.created']);
    }

    public function test_nonpositive_revision_version_is_rejected_by_postgresql(): void
    {
        $comment = Comment::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('comment_revisions')->insert(['id' => (string) Str::uuid(), 'comment_id' => $comment->id, 'comment_version' => 0, 'action' => 'created', 'body' => 'Texte fictif', 'occurred_at' => now()]);
    }
}
