<?php

namespace App\Models;

use App\Enums\Identity\HelpAvailability;
use Carbon\CarbonImmutable;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $directory_visible
 * @property HelpAvailability $help_availability
 * @property string|null $contribution_interests
 * @property CarbonImmutable|null $availability_updated_at
 */
#[Fillable(['bio', 'country', 'primary_language', 'github_url'])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var array<string, mixed> */
    protected $attributes = ['bio' => '', 'primary_language' => 'fr', 'lock_version' => 0,
        'directory_visible' => false, 'help_availability' => 'not_specified'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['lock_version' => 'integer', 'hidden_at' => 'immutable_datetime', 'directory_visible' => 'boolean',
            'help_availability' => HelpAvailability::class, 'availability_updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
