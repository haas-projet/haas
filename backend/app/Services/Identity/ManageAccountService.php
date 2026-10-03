<?php

namespace App\Services\Identity;

use App\Data\Identity\ManageAccountData;
use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Exceptions\Identity\AccountVersionConflict;
use App\Exceptions\Identity\AuthenticationStorageFailed;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class ManageAccountService
{
    public function update(User $actor, string $userId, ManageAccountData $data): User
    {
        try {
            return DB::transaction(function () use ($actor, $userId, $data): User {
                // Sérialise les décisions administratives, y compris deux retraits du dernier admin.
                DB::table('administration_guard')->where('id', 1)->lockForUpdate()->sole();
                $users = User::whereIn('id', [$actor->id, $userId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $currentActor = $users->get($actor->id);
                if ($currentActor === null) {
                    throw new AuthorizationException;
                }
                Gate::forUser($currentActor)->authorize('administer', User::class);
                $target = $users->get($userId) ?? User::whereKey($userId)->firstOrFail();
                if ($target->security_version !== $data->version || $target->{$data->field}->value === $data->value) {
                    throw new AccountVersionConflict;
                }
                $removesAdmin = $target->role === Role::Admin && $target->status === AccountStatus::Active && $target->hasVerifiedEmail()
                    && (($data->field === 'role' && $data->value !== Role::Admin->value) || ($data->field === 'status' && $data->value !== AccountStatus::Active->value));
                if ($removesAdmin && ! User::where('role', Role::Admin)->where('status', AccountStatus::Active)->whereNotNull('email_verified_at')->where('id', '!=', $target->id)->exists()) {
                    throw new AccountVersionConflict;
                }
                $previous = $target->{$data->field}->value;
                $target->{$data->field} = $data->value;
                $target->security_version++;
                $target->remember_token = Str::random(60);
                $target->save();
                DB::table('sessions')->where('user_id', $target->id)->delete();
                $decisionId = (string) Str::uuid();
                DB::table('account_decisions')->insert([
                    'id' => $decisionId, 'actor_id' => $currentActor->id, 'user_id' => $target->id,
                    'field' => $data->field, 'previous' => $previous, 'current' => $data->value,
                    'security_version' => $target->security_version, 'reason_encrypted' => Crypt::encryptString($data->reason), 'created_at' => now()->utc(),
                ]);
                $revision = (int) DB::table('content_revisions')->where('resource_type', 'user')->where('resource_id', $target->id)->max('revision') + 1;
                DB::table('content_revisions')->insert([
                    'id' => (string) Str::uuid(), 'actor_id' => $currentActor->id, 'resource_type' => 'user', 'resource_id' => $target->id,
                    'revision' => $revision, 'action' => 'account.'.$data->field.'_changed',
                    'metadata' => json_encode(['decision_id' => $decisionId, 'security_version' => $target->security_version], JSON_THROW_ON_ERROR), 'occurred_at' => now()->utc(),
                ]);

                return $target;
            });
        } catch (QueryException) {
            throw new AuthenticationStorageFailed('La décision administrative n’a pas été enregistrée.');
        }
    }
}
