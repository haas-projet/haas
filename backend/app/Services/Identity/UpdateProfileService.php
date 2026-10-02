<?php

namespace App\Services\Identity;

use App\Data\Audit\ProfileRevisionData;
use App\Data\Identity\UpdateProfileData;
use App\Exceptions\Identity\ProfileStorageFailed;
use App\Exceptions\Identity\ProfileUpdateRejected;
use App\Exceptions\Identity\ProfileVersionConflict;
use App\Models\Profile;
use App\Models\Technology;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateProfileService
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function update(User $actor, UpdateProfileData $data): User
    {
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
