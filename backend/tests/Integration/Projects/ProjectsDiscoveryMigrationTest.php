<?php

namespace Tests\Integration\Projects;

use App\Models\Comment;
use App\Models\HelpRequest;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Tests\PostgresTestCase;

final class ProjectsDiscoveryMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_upgrade_and_safe_rollback_preserve_legacy_identity_and_discussions(): void
    {
        $migration = $this->migration();
        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $this->assertFalse(Schema::hasTable('projects'));
        $this->assertFalse(Schema::hasColumn('help_requests', 'project_id'));
        $this->assertFalse(Schema::hasColumn('profiles', 'directory_visible'));

        $owner = User::factory()->verified()->create();
        $contributor = User::factory()->verified()->create();
        // The legacy table cannot receive the new model defaults before up().
        DB::table('profiles')->insert(['user_id' => $owner->id, 'bio' => 'Profil fictif historique.', 'country' => null,
            'primary_language' => 'fr', 'github_url' => null, 'lock_version' => 3,
            'created_at' => '2026-10-10 12:00:00+00', 'updated_at' => '2026-10-10 12:00:00+00']);
        $request = HelpRequest::factory()->create(['author_id' => $owner->id, 'state' => 'resolved', 'lock_version' => 7]);
        $comment = Comment::factory()->create(['request_id' => $request->id, 'author_id' => $contributor->id]);
        $proposal = Proposal::factory()->create(['request_id' => $request->id, 'author_id' => $contributor->id,
            'state' => 'accepted', 'lock_version' => 4]);
        $resolution = Resolution::factory()->create(['request_id' => $request->id, 'proposal_id' => $proposal->id,
            'accepted_by' => $owner->id, 'environment' => 'Contexte historique fictif.', 'proposal_version' => 4]);
        $rows = ['users' => ['id', $owner->id], 'profiles' => ['user_id', $owner->id],
            'help_requests' => ['id', $request->id], 'comments' => ['id', $comment->id],
            'proposals' => ['id', $proposal->id], 'resolutions' => ['id', $resolution->id]];
        $snapshots = [];
        foreach ($rows as $table => [$key, $id]) {
            $snapshots[] = ['table' => $table, 'key' => $key, 'id' => $id,
                'original' => (array) DB::table($table)->where($key, $id)->sole()];
        }

        foreach ([1, 2] as $iteration) {
            (new ReflectionMethod($migration, 'up'))->invoke($migration);
            foreach ($snapshots as $snapshot) {
                ['table' => $table, 'key' => $key, 'id' => $id] = $snapshot;
                $current = (array) DB::table($table)->where($key, $id)->sole();
                foreach ($snapshot['original'] as $field => $value) {
                    $this->assertSame($value, $current[$field], $table.'.'.$field.' / '.$iteration);
                }
            }
            $profile = DB::table('profiles')->where('user_id', $owner->id)->sole();
            $this->assertFalse($profile->directory_visible);
            $this->assertSame('not_specified', $profile->help_availability);
            $this->assertNull($profile->contribution_interests);
            $this->assertNull($profile->availability_updated_at);
            $this->assertNull(DB::table('help_requests')->where('id', $request->id)->value('project_id'));
            $this->assertDatabaseCount('projects', 0);
            $this->assertDatabaseCount('project_technologies', 0);
            $this->assertDatabaseCount('content_revisions', 0);
            $this->assertDatabaseCount('proposal_revisions', 0);
            $this->assertDatabaseCount('notification_outbox', 0);
            $this->assertDatabaseCount('internal_notifications', 0);
            if ($iteration === 1) {
                (new ReflectionMethod($migration, 'down'))->invoke($migration);
                foreach ($snapshots as $snapshot) {
                    $this->assertSame($snapshot['original'], (array) DB::table($snapshot['table'])
                        ->where($snapshot['key'], $snapshot['id'])->sole());
                }
            }
        }
    }

    /** @param array<string, string|bool> $choice */
    #[DataProvider('knownPreferences')]
    public function test_rollback_refuses_to_lose_a_known_discovery_choice(array $choice): void
    {
        $profile = Profile::factory()->create();
        DB::table('profiles')->where('user_id', $profile->user_id)->update($choice);
        $original = (array) DB::table('profiles')->where('user_id', $profile->user_id)->sole();
        $this->assertRollbackRefused();
        $this->assertSame($original, (array) DB::table('profiles')->where('user_id', $profile->user_id)->sole());
        $this->assertDatabaseCount('projects', 0);
    }

    /** @return iterable<string, array{array<string, string|bool>}> */
    public static function knownPreferences(): iterable
    {
        yield 'explicit opt in' => [['directory_visible' => true]];
        yield 'available' => [['help_availability' => 'available', 'availability_updated_at' => '2026-10-11 12:00:00+00']];
        yield 'unavailable' => [['help_availability' => 'unavailable', 'availability_updated_at' => '2026-10-11 12:00:00+00']];
        yield 'known empty interests' => [['contribution_interests' => '']];
        yield 'known interests' => [['contribution_interests' => 'Documentation fictive.']];
        yield 'known declaration date' => [['availability_updated_at' => '2026-10-11 12:00:00+00']];
    }

    #[DataProvider('projectStates')]
    public function test_rollback_preserves_every_project_and_optional_request_link(string $state, bool $linked): void
    {
        $project = Project::factory()->create(['publication_state' => $state,
            'published_at' => $state === 'published' ? '2026-10-11 12:00:00+00' : null]);
        $request = HelpRequest::factory()->create(['author_id' => $project->owner_id, 'project_id' => $linked ? $project->id : null]);
        $original = (array) DB::table('projects')->where('id', $project->id)->sole();
        $this->assertRollbackRefused();
        $this->assertSame($original, (array) DB::table('projects')->where('id', $project->id)->sole());
        $this->assertSame($linked ? $project->id : null, $request->refresh()->project_id);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function projectStates(): iterable
    {
        foreach (['draft', 'published', 'archived'] as $state) {
            foreach ([false, true] as $linked) {
                yield $state.($linked ? ' linked' : ' standalone') => [$state, $linked];
            }
        }
    }

    private function assertRollbackRefused(): void
    {
        $level = DB::transactionLevel();
        try {
            $migration = $this->migration();
            (new ReflectionMethod($migration, 'down'))->invoke($migration);
            $this->fail('Les projets et choix connus doivent être conservés.');
        } catch (RuntimeException $exception) {
            $this->assertStringStartsWith('Retour BC01 refusé', $exception->getMessage());
        }
        $this->assertSame($level, DB::transactionLevel());
        $this->assertTrue(Schema::hasTable('projects'));
        $this->assertTrue(Schema::hasTable('project_technologies'));
        $this->assertTrue(Schema::hasColumn('help_requests', 'project_id'));
        foreach (['directory_visible', 'help_availability', 'contribution_interests', 'availability_updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('profiles', $column));
        }
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_11_220000_bc01_projects_and_discovery.php');
    }
}
