<?php

namespace App\Models;

use App\Enums\Projects\ProjectPublicationState;
use App\Enums\Projects\ProjectStage;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ProjectStage $project_stage
 * @property ProjectPublicationState $publication_state
 * @property int $lock_version
 * @property string|null $name
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $help_sought
 * @property string|null $demo_url
 * @property string|null $repository_url
 * @property string|null $moderation_reason
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $hidden_at
 * @property-read User $owner
 */
#[Fillable(['name', 'summary', 'description', 'project_stage', 'help_sought', 'demo_url', 'repository_url'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = ['project_stage' => 'idea', 'publication_state' => 'draft', 'lock_version' => 1];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<Technology, $this> */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class, 'project_technologies')->withTimestamps();
    }

    /** @return HasMany<HelpRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(HelpRequest::class, 'project_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['project_stage' => ProjectStage::class, 'publication_state' => ProjectPublicationState::class,
            'lock_version' => 'integer', 'published_at' => 'immutable_datetime', 'hidden_at' => 'immutable_datetime'];
    }
}
