<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Models\Capsules\Capsule;
use App\Models\Capsules\Favorite;
use App\Models\User;
use App\Policies\FavoritePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * B28 — PUT /capsules/{capsule}/favorite : idempotent. Deux PUT = une
 * seule ligne. L'unicité est assurée par `favorites_user_capsule_unique`.
 */
final class MarkFavoriteService
{
    public function __construct(private readonly FavoritePolicy $policy) {}

    public function handle(User $actor, Capsule $capsule): Favorite
    {
        if (! $this->policy->mark($actor, $capsule)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $capsule): Favorite {
            $existing = Favorite::query()
                ->where('user_id', $actor->id)
                ->where('capsule_id', $capsule->id)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return $existing;
            }
            $favorite = new Favorite;
            $favorite->forceFill([
                'id' => (string) Str::uuid(),
                'user_id' => $actor->id,
                'capsule_id' => $capsule->id,
                'created_at' => now()->utc(),
            ])->save();

            return $favorite;
        });
    }
}
