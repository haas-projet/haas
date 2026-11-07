<?php

namespace App\Services\Projects;

use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\Projects\UpdateProjectData;
use App\Enums\Projects\ProjectPublicationState;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Exceptions\Projects\ProjectVersionConflict;
use App\Models\Project;
use App\Models\User;
use App\Services\Audit\ProjectAuditWriter;
use App\Services\Idempotency\IdempotencyService;
use App\Support\Projects\ProjectContent;
use Illuminate\Support\Facades\Gate;

final class UpdateProjectService
{
    public function __construct(private readonly IdempotencyService $idempotency, private readonly ProjectAuditWriter $audit,
        private readonly CreateProjectService $creation) {}

    public function update(User $actor, string $id, UpdateProjectData $data, IdempotencyKey $key): Project
    {
        $id = strtolower($id);

        return $this->idempotency->execute($actor, new IdempotencyData('PATCH /api/v1/projects/'.$id, $key, $data->payload()),
            function (User $current) use ($id): void {
                $this->editable($current, $id);
            },
            function (User $current) use ($id, $data): StoredCommandResult {
                $project = $this->editable($current, $id);
                if ($project->lock_version !== $data->lockVersion) {
                    throw new ProjectVersionConflict;
                }
                /** @var list<string> $oldIds */
                $oldIds = $project->technologies()->orderBy('technologies.id')->pluck('technologies.id')->all();
                $ids = $data->technologyIds ?? $oldIds;
                $this->creation->lockTechnologies($ids);
                $project->fill($data->attributes);
                ProjectContent::validate([...$project->only(ProjectContent::FIELDS), 'technology_ids' => $ids], $project->publication_state === ProjectPublicationState::Published);
                $fields = array_keys($project->getDirty());
                if ($ids !== $oldIds) {
                    $fields[] = 'technology_ids';
                }
                $project->lock_version++;
                $project->save();
                if ($data->technologyIds !== null) {
                    $project->technologies()->sync($ids);
                }
                $this->audit->record($current, $project, false, $fields);

                return new StoredCommandResult(200, ['project_id' => $id], $project->lock_version);
            },
            function (User $current, StoredCommandResult $result) use ($id): Project {
                if ($result->status !== 200 || $result->references !== ['project_id' => $id]) {
                    throw new IdempotencyStorageFailed('Résultat de projet mémorisé invalide.');
                }
                $project = $this->editable($current, $id);
                if ($project->lock_version !== $result->version) {
                    throw new ProjectVersionConflict;
                }

                return $project->load(['owner:id,handle', 'technologies' => fn ($query) => $query->orderBy('slug')->orderBy('technologies.id')]);
            });
    }

    private function editable(User $actor, string $id): Project
    {
        $project = Project::whereKey($id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize('update', $project);

        return $project;
    }
}
