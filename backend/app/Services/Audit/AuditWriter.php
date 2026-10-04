<?php

namespace App\Services\Audit;

use App\Data\Audit\ProfileRevisionData;
use App\Exceptions\Audit\AuditStorageFailed;
use App\Models\HelpRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;

final class AuditWriter
{
    public function helpRequestCreated(User $actor, HelpRequest $request): void
    {
        $this->requireTransaction($actor, $request);
        try {
            $owner = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('create', HelpRequest::class);
            $current = HelpRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($current->author_id !== $owner->id) {
                throw new AuthorizationException;
            }
            DB::table('content_revisions')->insert([
                'id' => (string) Str::uuid(), 'actor_id' => $owner->id, 'resource_type' => 'help_request',
                'resource_id' => $current->id, 'revision' => 1, 'action' => 'help_request.created',
                'metadata' => json_encode(['state' => $current->state->value, 'help_intent' => $current->help_intent->value,
                    'request_version' => $current->lock_version, 'technology_count' => $current->technologies()->count()], JSON_THROW_ON_ERROR),
                'occurred_at' => now()->utc(),
            ]);
        } catch (QueryException) {
            throw new AuditStorageFailed('Échec du stockage de la révision.');
        }
    }

    public function profileUpdated(User $actor, Profile $profile, ProfileRevisionData $data): void
    {
        $this->requireTransaction($actor, $profile);
        if ($actor->id !== $profile->user_id) {
            throw new AuthorizationException;
        }
        try {
            DB::transaction(function () use ($actor, $profile, $data): void {
                $owner = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($owner)->authorize('updateProfile', $owner);
                $current = Profile::whereKey($profile->user_id)->lockForUpdate()->firstOrFail();
                $fields = $data->changedFields;
                sort($fields);
                // Aucune valeur textuelle libre, URL, empreinte de contenu ou donnée de requête.
                $this->append($owner, $current, 'profile.updated', [
                    'changed_fields' => $fields,
                    'profile_version' => $current->lock_version,
                    'technology_count' => $owner->technologies()->count(),
                ]);
            });
        } catch (QueryException) {
            throw new AuditStorageFailed('Échec du stockage de la révision.');
        }
    }

    /** Primitive interne pour un futur service de retrait ; aucune route publique. */
    public function redactProfileHistory(User $actor, Profile $profile): int
    {
        $this->requireTransaction($actor, $profile);
        try {
            return DB::transaction(function () use ($actor, $profile): int {
                // Même ordre pour deux modérateurs ; les appelants doivent respecter cet ordre.
                $users = User::whereIn('id', [$actor->id, $profile->user_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $currentActor = $users->get($actor->id);
                if ($currentActor === null) {
                    throw new AuthorizationException;
                }
                Gate::forUser($currentActor)->authorize('moderate', User::class);
                $current = Profile::whereKey($profile->user_id)->lockForUpdate()->firstOrFail();
                $count = $this->history($current)->whereNull('redacted_at')->where('action', '!=', 'history.redacted')
                    ->update(['metadata' => '{}', 'redacted_at' => now()->utc()]);
                if ($count > 0) {
                    $this->append($currentActor, $current, 'history.redacted', ['purged_revisions' => $count]);
                }

                return $count;
            });
        } catch (QueryException) {
            // Ne jamais chaîner l'erreur SQL ni recopier les anciennes données purgées.
            throw new AuditStorageFailed('Échec du retrait des métadonnées de révision.');
        }
    }

    private function requireTransaction(User $actor, Model $profile): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'pgsql' || $connection->transactionLevel() < 1
            || $actor->getConnection() !== $connection || $profile->getConnection() !== $connection
            || ! $actor->exists || ! $profile->exists) {
            throw new LogicException('Audit requis dans la transaction PostgreSQL métier, avec des modèles persistés sur la même connexion.');
        }
    }

    /** @param array<string, int|list<string>> $metadata */
    private function append(User $actor, Profile $profile, string $action, array $metadata): void
    {
        // Le verrou sur la ressource reste tenu jusqu'au commit métier, y compris au premier événement.
        $revision = (int) $this->history($profile)->max('revision') + 1;
        DB::table('content_revisions')->insert([
            'id' => (string) Str::uuid(), 'actor_id' => $actor->id,
            'resource_type' => 'profile', 'resource_id' => $profile->user_id,
            'revision' => $revision, 'action' => $action,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'occurred_at' => now()->utc(),
        ]);
    }

    private function history(Profile $profile): Builder
    {
        return DB::table('content_revisions')->where('resource_type', 'profile')->where('resource_id', $profile->user_id);
    }
}
