<?php

namespace App\Services\Projects;

use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\Projects\CreateProjectData;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Exceptions\Projects\ProjectVersionConflict;
use App\Models\Project;
use App\Models\Technology;
use App\Models\User;
use App\Services\Audit\ProjectAuditWriter;
use App\Services\Idempotency\IdempotencyService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CreateProjectService
{
    public function __construct(private readonly IdempotencyService $idempotency, private readonly ProjectAuditWriter $audit) {}

    public function create(User $actor, CreateProjectData $data, IdempotencyKey $key): Project
    {
        return $this->idempotency->execute($actor, new IdempotencyData('POST /api/v1/projects', $key, $data->payload()),
            function (User $current): void {
                Gate::forUser($current)->authorize('create', Project::class);
            },
            function (User $current) use ($data): StoredCommandResult {
                $this->lockTechnologies($data->technologyIds);
                $project = new Project($data->attributes);
                $project->owner_id = $current->id;
                $project->save();
                $project->technologies()->attach($data->technologyIds);
                $this->audit->record($current, $project, true);

                return new StoredCommandResult(201, ['project_id' => $project->id], $project->lock_version);
            },
            function (User $current, StoredCommandResult $result): Project {
                if ($result->status !== 201 || array_keys($result->references) !== ['project_id']) {
                    throw new IdempotencyStorageFailed('Résultat de projet mémorisé invalide.');
                }
                $project = Project::whereKey($result->references['project_id'])->lockForUpdate()->firstOrFail();
                Gate::forUser($current)->authorize('view', $project);
                if ($project->owner_id !== $current->id || $project->lock_version !== $result->version) {
                    throw new ProjectVersionConflict;
                }

                return $project->load(['owner:id,handle', 'technologies' => fn ($query) => $query->orderBy('slug')->orderBy('technologies.id')]);
            });
    }

    /** @param list<string> $ids */
    public function lockTechnologies(array $ids): void
    {
        $found = Technology::whereIn('id', $ids)->orderBy('id')->sharedLock()->pluck('id')->all();
        if (count($found) !== count($ids)) {
            throw ValidationException::withMessages(['technology_ids' => 'Une technologie sélectionnée est indisponible. Rechargez la liste.']);
        }
    }
}
