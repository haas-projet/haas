<?php

namespace App\Models;

use Database\Factories\TechnologyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['slug', 'name'])]
class Technology extends Model
{
    /** @use HasFactory<TechnologyFactory> */
    use HasFactory, HasUuids;

    /** @return Attribute<string, string> */
    protected function slug(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => strtolower(trim($value)));
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_technologies');
    }
}
