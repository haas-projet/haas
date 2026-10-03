<?php

namespace App\Services\Identity;

use App\Data\Audit\ProfileRevisionData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\Identity\UpdateProfileData;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Exceptions\Identity\ProfileStorageFailed;
use App\Exceptions\Identity\ProfileUpdateRejected;
use App\Exceptions\Identity\ProfileVersionConflict;
use App\Models\Profile;
use App\Models\Technology;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Idempotency\IdempotencyService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateProfileService
{
    public function __construct(private readonly AuditWriter $audit, private readonly IdempotencyService $idempotency) {}

    public function update(User $actor, UpdateProfileData $data, ?IdempotencyKey $key = null): User
    {
        if ($key !== null) {
            $payload = ['lock_version' => $data->lockVersion, 'attributes' => $data->attributes];
            if ($data->technologyIds !== null) {
                $technologies = array_map('strtolower', $data->technologyIds);
                sort($technologies);
                $payload['technology_ids'] = $technologies;
            }

            return $this->idempotency->execute($actor, new IdempotencyData('PATCH /api/v1/members/'.strtolower($actor->id).'/profile', $key, $payload),
                function (User $current): void {
                    Gate::forUser($current)->authorize('updateProfile', $current);
                },
                function (User $current) use ($data): StoredCommandResult {
                    $updated = $this->update($current, $data);

                    return new StoredCommandResult(200, ['profile_id' => $updated->id], $updated->profile?->lock_version);
                },
                function (User $current, StoredCommandResult $result): User {
                    if ($result->references !== ['profile_id' => $current->id] || $result->status !== 200) {
                        throw new IdempotencyStorageFailed('Résultat de profil mémorisé invalide.');
                    }
                    $profile = $current->profile()->lockForUpdate()->firstOrFail();
                    if ($profile->lock_version !== $result->version) {
                        throw new ProfileVersionConflict;
                    }

                    return $current->setRelation('profile', $profile)->load(['technologies' => fn ($query) => $query->orderBy('slug')->orderBy('technologies.id')]);
                });
        }
        try {
            return DB::transaction(function () use ($actor, $data): User {
                $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($user)->authorize('updateProfile', $user);
                $profile = $user->profile()->lockForUpdate()->first();
                if ($profile === null) {
                    $profile = new Profile;
                    $profile->user_id = $user->id;
                }
                if ($profile->lock_version !== $data->lockVersion) {
                    throw new ProfileVersionConflict;
                }
                if ($data->technologyIds !== null) {
                    $technologies = Technology::whereIn('id', $data->technologyIds)->orderBy('id')->sharedLock()->get(['id']);
                    if ($technologies->count() !== count($data->technologyIds)) {
                        throw new ProfileUpdateRejected;
                    }
                }
                $profile->fill($data->attributes);
                $changedFields = array_keys($profile->getDirty());
                $changedFields = array_values(array_intersect($changedFields, ['bio', 'country', 'primary_language', 'github_url']));
                $profile->lock_version++;
                $profile->save();
                if ($data->technologyIds !== null) {
                    $changes = $user->technologies()->sync($data->technologyIds);
                    if ($changes['attached'] !== [] || $changes['detached'] !== [] || $changes['updated'] !== []) {
                        $changedFields[] = 'technology_ids';
                    }
                }
                $this->audit->profileUpdated($user, $profile, new ProfileRevisionData($changedFields));

                return $user->setRelation('profile', $profile)->load(['technologies' => fn ($query) => $query->orderBy('slug')->orderBy('technologies.id')]);
            });
        } catch (QueryException $exception) {
            // Aucune copie de biographie, d'URL ou de bindings SQL dans les logs.
            throw new ProfileStorageFailed('Échec du stockage du profil ; SQLSTATE '.$exception->getCode().'.');
        }
    }
}
