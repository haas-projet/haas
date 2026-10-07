<?php

namespace App\Models;

use App\Enums\HelpRequests\HelpIntent;
use App\Enums\HelpRequests\HelpRequestState;
use Database\Factories\HelpRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property HelpIntent $help_intent
 * @property HelpRequestState $state
 */
#[Fillable(['title', 'goal', 'expected', 'observed', 'attempts', 'environment', 'code', 'code_language', 'primary_language', 'reproduction_url'])]
class HelpRequest extends Model
{
    /** @use HasFactory<HelpRequestFactory> */
    use HasFactory, HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = ['lock_version' => 1];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsToMany<Technology, $this> */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class, 'request_technologies', 'request_id', 'technology_id')
            ->withPivot('version_label')
            ->withTimestamps();
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'request_id');
    }

    /** @return HasMany<Proposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'request_id');
    }

    /** @return HasMany<Resolution, $this> */
    public function resolutions(): HasMany
    {
        return $this->hasMany(Resolution::class, 'request_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'help_intent' => HelpIntent::class,
            'hidden_at' => 'immutable_datetime',
            'state' => HelpRequestState::class,
            'lock_version' => 'integer',
        ];
    }
}
