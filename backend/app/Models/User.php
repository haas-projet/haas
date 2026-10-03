<?php

namespace App\Models;

use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Notifications\Identity\ResetAccountPassword;
use App\Notifications\Identity\VerifyAccountEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * @property AccountStatus $status
 * @property Role $role
 */
#[Fillable(['handle', 'email', 'password'])]
#[Hidden(['email', 'password', 'remember_token', 'role', 'status', 'email_verified_at', 'is_demo', 'name', 'security_version'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = ['role' => 'member', 'status' => 'active', 'is_demo' => false, 'security_version' => 0];

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyAccountEmail($this->email));
    }

    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        $this->notify(new ResetAccountPassword($this->email, $token));
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => Str::lower(trim($value)));
    }

    /** @return Attribute<string, string> */
    protected function handle(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => trim($value));
    }

    /** @return HasOne<Profile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /** @return BelongsToMany<Technology, $this> */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class, 'user_technologies');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'status' => AccountStatus::class,
            'is_demo' => 'boolean',
            'security_version' => 'integer',
        ];
    }
}
