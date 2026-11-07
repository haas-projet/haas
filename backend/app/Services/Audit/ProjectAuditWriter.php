<?php

namespace App\Services\Audit;

use App\Exceptions\Audit\AuditStorageFailed;
use App\Models\Project;
use App\Models\User;
use App\Support\Projects\ProjectContent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;

final class ProjectAuditWriter
{
    /** @param list<string> $fields */
    public function record(User $actor, Project $project, bool $created, array $fields = []): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'pgsql' || $connection->transactionLevel() < 1
            || ! $actor->exists || ! $project->exists || $actor->getConnection() !== $connection
            || $project->getConnection() !== $connection || array_diff($fields, [...ProjectContent::FIELDS, 'technology_ids']) !== []) {
            throw new LogicException('Audit projet requis dans la transaction métier avec des champs connus.');
        }
        $owner = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $current = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($owner)->authorize($created ? 'create' : 'update', $created ? Project::class : $current);
        if ($current->owner_id !== $owner->id) {
            throw new AuthorizationException;
        }
        try {
            $revision = (int) DB::table('content_revisions')->where('resource_type', 'project')->where('resource_id', $current->id)->max('revision') + 1;
            sort($fields, SORT_STRING);
            DB::table('content_revisions')->insert(['id' => (string) Str::uuid(), 'actor_id' => $owner->id,
                'resource_type' => 'project', 'resource_id' => $current->id, 'revision' => $revision,
                'action' => $created ? 'project.created' : 'project.updated',
                'metadata' => json_encode(['project_version' => $current->lock_version, 'changed_fields' => $fields,
                    'technology_count' => $current->technologies()->count()], JSON_THROW_ON_ERROR), 'occurred_at' => now()->utc()]);
        } catch (QueryException) {
            throw new AuditStorageFailed('Échec du stockage de la révision du projet.');
        }
    }
}
