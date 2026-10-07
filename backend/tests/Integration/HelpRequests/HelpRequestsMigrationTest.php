<?php

namespace Tests\Integration\HelpRequests;

use App\Models\Comment;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\PostgresTestCase;

final class HelpRequestsMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_b11_can_be_rolled_back_and_reapplied_without_removing_identity_data(): void
    {
        $author = User::factory()->create();
        $technology = Technology::factory()->create();
        $request = HelpRequest::factory()->create(['author_id' => $author->id]);
        $request->technologies()->attach($technology);
        Comment::factory()->create(['request_id' => $request->id]);
        $proposal = Proposal::factory()->create(['request_id' => $request->id]);
        Resolution::factory()->create(['proposal_id' => $proposal->id]);

        $paths = glob(database_path('migrations/2026_10_03_180*.php'));
        $this->assertIsArray($paths);
        $this->assertCount(6, $paths);
        // Les extensions dépendent de B11 : descendre les enfants avant le parent, puis remonter dans l'ordre.
        $paths[] = database_path('migrations/2026_10_04_000014_b14_extend_help_request_content.php');
        $paths[] = database_path('migrations/2026_10_04_000016_b16_create_help_request_revisions.php');
        $paths[] = database_path('migrations/2026_10_04_000017_b17_comment_revisions_and_events.php');
        $migrations = array_map(function (string $path): Migration {
            $migration = require $path;
            $this->assertInstanceOf(Migration::class, $migration);

            return $migration;
        }, $paths);

        foreach (array_reverse($migrations) as $migration) {
            (new ReflectionMethod($migration, 'down'))->invoke($migration);
        }

        foreach (['help_requests', 'request_technologies', 'comments', 'proposals', 'resolutions'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertModelExists($author);
        $this->assertModelExists($technology);

        foreach ($migrations as $migration) {
            (new ReflectionMethod($migration, 'up'))->invoke($migration);
        }

        $this->assertTrue(Schema::hasColumn('help_requests', 'help_intent'));
        $this->assertTrue(Schema::hasTable('help_request_revisions'));
        $resolution = Resolution::factory()->create();
        $this->assertModelExists($resolution);
        $this->assertSame($resolution->request()->firstOrFail()->author_id, $resolution->accepted_by);
        $this->assertSame(1, $resolution->proposal()->firstOrFail()->lock_version);
    }

    public function test_version_upgrade_preserves_existing_content_and_request_version(): void
    {
        $request = HelpRequest::factory()->create(['lock_version' => 9]);
        $comment = Comment::factory()->create(['request_id' => $request->id]);
        $proposal = Proposal::factory()->create(['request_id' => $request->id]);
        $migration = require database_path('migrations/2026_10_03_180500_b11_add_collaboration_versions.php');
        $this->assertInstanceOf(Migration::class, $migration);

        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $this->assertFalse(Schema::hasColumn('comments', 'lock_version'));
        $this->assertFalse(Schema::hasColumn('proposals', 'lock_version'));
        (new ReflectionMethod($migration, 'up'))->invoke($migration);

        $this->assertSame(9, $request->refresh()->lock_version);
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'body' => $comment->body, 'lock_version' => 1]);
        $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'diagnosis' => $proposal->diagnosis, 'lock_version' => 1]);
    }
}
