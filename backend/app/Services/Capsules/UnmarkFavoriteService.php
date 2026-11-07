<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Models\Capsules\Capsule;
use App\Models\Capsules\Favorite;
use App\Models\User;
use App\Policies\FavoritePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * B28 — DELETE /capsules/{capsule}/favorite : idempotent. L'absence de
 * ligne renvoie un succès sans effet.
 */
final class UnmarkFavoriteService
{
    public function __construct(private readonly FavoritePolicy $policy) {}

    public function handle(User $actor, Capsule $capsule): int
    {
        if (! $this->policy->unmark($actor, $capsule)) {
            throw new AuthorizationException;
        }

        return (int) DB::transaction(fn (): int => Favorite::query()
            ->where('user_id', $actor->id)
            ->where('capsule_id', $capsule->id)
            ->delete());
    }
}
