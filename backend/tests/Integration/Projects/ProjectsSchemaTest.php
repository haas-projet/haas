<?php

namespace Tests\Integration\Projects;

use App\Enums\Identity\HelpAvailability;
use App\Enums\Projects\ProjectPublicationState;
use App\Enums\Projects\ProjectStage;
use App\Models\HelpRequest;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;

final class ProjectsSchemaTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_fresh_schema_has_private_defaults_and_separate_states(): void
    {
        $this->assertTrue(Schema::hasTable('projects'));
        $this->assertTrue(Schema::hasTable('project_technologies'));
        $project = Project::factory()->create();
        $this->assertTrue(Str::isUuid($project->id));
        $this->assertSame(ProjectStage::Idea, $project->project_stage);
        $this->assertSame(ProjectPublicationState::Draft, $project->publication_state);
        $this->assertSame(1, $project->lock_version);
        $this->assertNull($project->published_at);
        $this->assertNull($project->hidden_at);
        $this->assertNull($project->moderation_reason);
        $this->assertNull($project->demo_url);
        $this->assertNull($project->repository_url);
        $this->assertTrue($project->owner->hasVerifiedEmail());
        $profile = Profile::factory()->create();
        $this->assertFalse($profile->refresh()->directory_visible);
        $this->assertSame(HelpAvailability::NotSpecified, $profile->help_availability);
        $this->assertNull($profile->contribution_interests);
        $this->assertNull($profile->availability_updated_at);
        $this->assertNull(HelpRequest::factory()->create()->project_id);
    }

    public function test_incomplete_draft_and_never_published_archive_keep_unknown_fields(): void
    {
        $project = Project::factory()->create(['name' => null, 'summary' => null, 'description' => null]);
        $this->assertNull($project->name);
        $this->assertNull($project->summary);
        $this->assertNull($project->description);
        $project->forceFill(['publication_state' => ProjectPublicationState::Archived])->save();
        $this->assertSame(ProjectPublicationState::Archived, $project->refresh()->publication_state);
        $this->assertNull($project->published_at);
    }

    public function test_archiving_preserves_the_known_publication_date_and_declared_stage(): void
    {
        $project = Project::factory()->published()->create(['project_stage' => ProjectStage::Prototype]);
        $published = $project->published_at;
        $this->assertNotNull($published);
        $project->forceFill(['publication_state' => ProjectPublicationState::Archived])->save();
        $archivedPublication = $project->refresh()->published_at;
        $this->assertNotNull($archivedPublication);
        $this->assertTrue($published->equalTo($archivedPublication));
        $this->assertSame(ProjectStage::Prototype, $project->project_stage);
    }

    public function test_shared_technologies_are_unique_for_each_project(): void
    {
        $project = Project::factory()->create();
        $technology = Technology::factory()->create();
        $project->technologies()->attach($technology);
        $this->assertSame([$technology->id], $project->technologies()->pluck('technologies.id')->all());
        $this->expectException(QueryException::class);
        $project->technologies()->attach($technology);
    }

    #[DataProvider('invalidReferences')]
    public function test_foreign_keys_reject_unknown_references(string $target): void
    {
        $project = Project::factory()->create();
        $technology = Technology::factory()->create();
        $unknown = (string) Str::uuid();
        $this->expectException(QueryException::class);
        match ($target) {
            'owner' => Project::factory()->create(['owner_id' => $unknown]),
            'project technology' => DB::table('project_technologies')->insert(['project_id' => $unknown, 'technology_id' => $technology->id]),
            'technology' => DB::table('project_technologies')->insert(['project_id' => $project->id, 'technology_id' => $unknown]),
            'request project' => HelpRequest::factory()->create(['author_id' => $project->owner_id, 'project_id' => $unknown]),
            default => throw new InvalidArgumentException('Cible de fixture inconnue.'),
        };
    }

    /** @return iterable<string, array{string}> */
    public static function invalidReferences(): iterable
    {
        foreach (['owner', 'project technology', 'technology', 'request project'] as $target) {
            yield $target => [$target];
        }
    }

    public function test_linked_request_belongs_to_the_same_owner_without_cascading_deletion(): void
    {
        $project = Project::factory()->published()->create();
        $request = HelpRequest::factory()->create(['author_id' => $project->owner_id, 'project_id' => $project->id]);
        $linkedProject = $request->refresh()->project;
        $this->assertNotNull($linkedProject);
        $this->assertSame($project->id, $linkedProject->id);
        $this->assertSame([$request->id], $project->requests()->pluck('id')->all());
        $this->expectException(QueryException::class);
        $project->delete();
    }

    public function test_foreign_request_author_cannot_be_attached_even_with_valid_ids(): void
    {
        $project = Project::factory()->published()->create();
        $other = User::factory()->verified()->create();
        $this->expectException(QueryException::class);
        HelpRequest::factory()->create(['author_id' => $other->id, 'project_id' => $project->id]);
    }

    public function test_linked_request_author_cannot_be_changed_to_a_foreign_owner(): void
    {
        $project = Project::factory()->published()->create();
        $request = HelpRequest::factory()->create(['author_id' => $project->owner_id, 'project_id' => $project->id]);
        $other = User::factory()->verified()->create();
        $this->expectException(QueryException::class);
        DB::table('help_requests')->where('id', $request->id)->update(['author_id' => $other->id]);
    }

    #[DataProvider('protectedParents')]
    public function test_parent_rows_with_project_references_cannot_be_deleted(string $target): void
    {
        $project = Project::factory()->create();
        $technology = Technology::factory()->create();
        $project->technologies()->attach($technology);
        $this->expectException(QueryException::class);
        match ($target) {
            'owner' => $project->owner->delete(),
            'technology' => $technology->delete(),
            'project' => $project->delete(),
            default => throw new InvalidArgumentException('Cible de fixture inconnue.'),
        };
    }

    /** @return iterable<string, array{string}> */
    public static function protectedParents(): iterable
    {
        foreach (['owner', 'technology', 'project'] as $target) {
            yield $target => [$target];
        }
    }

    /** @param array<string, string|int|null> $changes */
    #[DataProvider('invalidProjects')]
    public function test_postgresql_rejects_out_of_contract_project_values(array $changes): void
    {
        $project = Project::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('projects')->where('id', $project->id)->update($changes);
    }

    /** @return iterable<string, array{array<string, string|int|null>}> */
    public static function invalidProjects(): iterable
    {
        yield 'unknown stage' => [['project_stage' => 'validated']];
        yield 'unknown publication' => [['publication_state' => 'visible']];
        yield 'zero version' => [['lock_version' => 0]];
        yield 'negative version' => [['lock_version' => -1]];
        yield 'integer overflow' => [['lock_version' => 2147483648]];
        foreach (['name' => [3, 80], 'summary' => [30, 240], 'description' => [80, 4000]] as $field => [$min, $max]) {
            yield $field.' too short' => [[$field => str_repeat('é', $min - 1)]];
            yield $field.' too long' => [[$field => str_repeat('é', $max + 1)]];
        }
        yield 'long help text' => [['help_sought' => str_repeat('é', 1501)]];
        yield 'published without date' => [['publication_state' => 'published']];
        yield 'draft with publication date' => [['published_at' => '2026-10-11 12:00:00+00']];
        yield 'published without summary' => [['publication_state' => 'published', 'published_at' => '2026-10-11 12:00:00+00', 'summary' => null]];
        yield 'published without description' => [['publication_state' => 'published', 'published_at' => '2026-10-11 12:00:00+00', 'description' => null]];
        yield 'published without name' => [['publication_state' => 'published', 'published_at' => '2026-10-11 12:00:00+00', 'name' => null]];
        foreach (['demo_url', 'repository_url'] as $field) {
            yield $field.' plaintext' => [[$field => 'http://project.example.test/']];
            yield $field.' credentials' => [[$field => 'https://member:fictional@project.example.test/']];
            yield $field.' control' => [[$field => "https://project.example.test/\n"]];
            yield $field.' long' => [[$field => 'https://project.example.test/'.str_repeat('x', 2049)]];
        }
    }

    public function test_unicode_boundaries_and_inert_https_links_are_preserved(): void
    {
        foreach ([[3, 30, 80], [80, 240, 4000]] as [$name, $summary, $description]) {
            $project = Project::factory()->create(['name' => str_repeat('é', $name), 'summary' => str_repeat('é', $summary),
                'description' => str_repeat('é', $description), 'help_sought' => str_repeat('é', 1500),
                'demo_url' => 'https://project.example.test/demo?q=fixture#example', 'repository_url' => 'https://code.example.test/fictitious/project']);
            $this->assertSame(str_repeat('é', $name), $project->name);
            $this->assertSame(str_repeat('é', $summary), $project->summary);
            $this->assertSame(str_repeat('é', $description), $project->description);
            $this->assertSame('https://project.example.test/demo?q=fixture#example', $project->demo_url);
        }
        $this->assertDatabaseCount('projects', 2);
    }

    public function test_availability_does_not_enable_directory_opt_in_or_change_identity(): void
    {
        $profile = Profile::factory()->create();
        $user = $profile->user;
        $this->assertNotNull($user);
        $owner = $user->getAttributes();
        $profile->forceFill(['help_availability' => HelpAvailability::Available, 'availability_updated_at' => '2026-10-11 12:00:00+00',
            'contribution_interests' => str_repeat('é', 300)])->save();
        $this->assertFalse($profile->refresh()->directory_visible);
        $this->assertSame(HelpAvailability::Available, $profile->help_availability);
        $this->assertNotNull($profile->availability_updated_at);
        $this->assertSame('2026-10-11T12:00:00+00:00', $profile->availability_updated_at->toIso8601String());
        $this->assertSame($owner, $user->refresh()->getAttributes());
    }

    /** @param array<string, string|null> $changes */
    #[DataProvider('invalidPreferences')]
    public function test_postgresql_rejects_invalid_discovery_preferences(array $changes): void
    {
        $profile = Profile::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('profiles')->where('user_id', $profile->user_id)->update($changes);
    }

    /** @return iterable<string, array{array<string, string|null>}> */
    public static function invalidPreferences(): iterable
    {
        yield 'online is not declared availability' => [['help_availability' => 'online']];
        yield 'unknown availability' => [['help_availability' => 'unknown']];
        yield 'known availability without server date' => [['help_availability' => 'available']];
        yield 'oversized interests' => [['contribution_interests' => str_repeat('é', 301)]];
        yield 'null opt in' => [['directory_visible' => null]];
    }
}
