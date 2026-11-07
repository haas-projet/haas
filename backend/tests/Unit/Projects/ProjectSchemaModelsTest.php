<?php

namespace Tests\Unit\Projects;

use App\Enums\Identity\HelpAvailability;
use App\Enums\Projects\ProjectPublicationState;
use App\Enums\Projects\ProjectStage;
use App\Models\HelpRequest;
use App\Models\Profile;
use App\Models\Project;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

final class ProjectSchemaModelsTest extends TestCase
{
    public function test_project_defaults_do_not_publish_or_hide_content(): void
    {
        $this->assertTrue(class_exists(Project::class));
        $project = new Project;
        $this->assertSame(ProjectStage::Idea, $project->project_stage);
        $this->assertSame(ProjectPublicationState::Draft, $project->publication_state);
        $this->assertSame(1, $project->lock_version);
        foreach (['id', 'owner_id', 'published_at', 'hidden_at', 'moderation_reason'] as $field) {
            $this->assertNull($project->getAttribute($field));
        }
    }

    public function test_project_mass_assignment_excludes_identity_publication_and_moderation(): void
    {
        $this->assertTrue(class_exists(Project::class));
        $project = new Project;
        $this->assertSame(['name', 'summary', 'description', 'project_stage', 'help_sought', 'demo_url', 'repository_url'], $project->getFillable());
        foreach (['id', 'owner_id', 'publication_state', 'lock_version', 'published_at', 'hidden_at', 'moderation_reason', 'created_at', 'updated_at'] as $field) {
            $this->assertFalse($project->isFillable($field), $field);
        }
    }

    public function test_relations_use_the_shared_owner_and_technology_reference(): void
    {
        $this->assertTrue(class_exists(Project::class));
        $project = new Project;
        $this->assertInstanceOf(BelongsTo::class, $project->owner());
        $this->assertSame('owner_id', $project->owner()->getForeignKeyName());
        $this->assertInstanceOf(BelongsToMany::class, $project->technologies());
        $this->assertSame('project_technologies', $project->technologies()->getTable());
        $this->assertInstanceOf(HasMany::class, $project->requests());
        $this->assertSame('help_requests.project_id', $project->requests()->getQualifiedForeignKeyName());
        $request = new HelpRequest;
        $this->assertInstanceOf(BelongsTo::class, $request->project());
        $this->assertSame('project_id', $request->project()->getForeignKeyName());
        $this->assertFalse($request->isFillable('project_id'));
    }

    public function test_discovery_defaults_are_private_and_server_timestamp_is_protected(): void
    {
        $profile = new Profile;
        $this->assertFalse($profile->directory_visible);
        $this->assertSame(HelpAvailability::NotSpecified, $profile->help_availability);
        $this->assertNull($profile->contribution_interests);
        $this->assertNull($profile->availability_updated_at);
        $this->assertFalse($profile->isFillable('availability_updated_at'));
        $this->assertSame('boolean', $profile->getCasts()['directory_visible']);
        $this->assertSame(HelpAvailability::class, $profile->getCasts()['help_availability']);
        $this->assertSame('immutable_datetime', $profile->getCasts()['availability_updated_at']);
    }
}
