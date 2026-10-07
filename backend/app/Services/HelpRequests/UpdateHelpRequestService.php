<?php

namespace App\Services\HelpRequests;

use App\Data\Audit\HelpRequestRevisionData;
use App\Data\HelpRequests\UpdateHelpRequestData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Enums\HelpRequests\HelpRequestState;
use App\Exceptions\HelpRequests\HelpRequestCreationRejected;
use App\Exceptions\HelpRequests\HelpRequestVersionConflict;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Models\HelpRequest;
use App\Models\HelpRequestRevision;
use App\Models\Technology;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Idempotency\IdempotencyService;
use App\Support\HelpRequests\HelpRequestContent;
use Illuminate\Support\Facades\Gate;

final class UpdateHelpRequestService
{
    public function __construct(private readonly IdempotencyService $idempotency, private readonly AuditWriter $audit) {}

    public function update(User $actor, string $id, UpdateHelpRequestData $data, IdempotencyKey $key): HelpRequest
    {
        return $this->change($actor, $id, $data, $key, false);
    }

    public function publish(User $actor, string $id, int $version, IdempotencyKey $key): HelpRequest
    {
        return $this->change($actor, $id, new UpdateHelpRequestData($version), $key, true);
    }

    private function change(User $actor, string $id, UpdateHelpRequestData $data, IdempotencyKey $key, bool $publish): HelpRequest
    {
        $id = strtolower($id);
        $route = $publish ? 'POST /api/v1/requests/'.$id.'/publish' : 'PATCH /api/v1/requests/'.$id;

        return $this->idempotency->execute($actor, new IdempotencyData($route, $key, $data->payload()),
            function (User $current) use ($id): void {
                // Le verrou acteur est déjà pris par le socle ; les futures contributions verrouillent aussi ce parent.
                $this->editable($current, $id);
            },
            function (User $current) use ($id, $data, $publish): StoredCommandResult {
                $request = $this->editable($current, $id);
                if ($request->lock_version !== $data->lockVersion || ($publish && $request->state !== HelpRequestState::Draft)) {
                    throw new HelpRequestVersionConflict;
                }
                $technologies = $data->technologies ?? $this->technologies($request);
                $ids = array_column($technologies, 'id');
                $foundIds = Technology::whereIn('id', $ids)->orderBy('id')->sharedLock()->pluck('id')->all();
                if (count($foundIds) !== count($ids)) {
                    throw new HelpRequestCreationRejected;
                }
                $request->fill($data->attributes);
                if ($data->helpIntent !== null) {
                    $request->help_intent = $data->helpIntent;
                }
                if ($publish) {
                    $request->state = HelpRequestState::Open;
                }
                $content = [...$request->only(HelpRequestContent::FIELDS), 'help_intent' => $request->help_intent->value, 'technologies' => $technologies];
                HelpRequestContent::validate($content, $request->state !== HelpRequestState::Draft);
                $hasContributions = ! $publish && ($request->comments()->exists() || $request->proposals()->exists() || $request->resolutions()->exists());
                HelpRequestContent::validateNote($data->editNote, $hasContributions);
                $fields = array_keys($request->getDirty());
                $request->lock_version++;
                $request->save();
                if ($data->technologies !== null) {
                    $old = $this->technologies($request);
                    $pivot = [];
                    foreach ($technologies as $technology) {
                        $pivot[$technology['id']] = ['version_label' => $technology['version_label']];
                    }
                    $request->technologies()->sync($pivot);
                    usort($technologies, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
                    if ($old !== $technologies) {
                        $fields[] = 'technologies';
                    }
                }
                sort($fields);
                $revision = new HelpRequestRevision;
                $revision->forceFill(['request_id' => $id, 'actor_id' => $current->id, 'request_version' => $request->lock_version,
                    'action' => $publish ? 'published' : 'updated', 'is_public' => $request->state !== HelpRequestState::Draft,
                    'changed_fields' => $fields, 'edit_note' => $data->editNote, 'occurred_at' => now()->utc()])->save();
                $this->audit->helpRequestChanged($current, $request, new HelpRequestRevisionData($fields, $publish, $data->editNote !== null));

                return new StoredCommandResult(200, ['request_id' => $id], $request->lock_version);
            },
            function (User $current, StoredCommandResult $result) use ($id): HelpRequest {
                if ($result->status !== 200 || $result->references !== ['request_id' => $id]) {
                    throw new IdempotencyStorageFailed('Résultat de demande mémorisé invalide.');
                }
                $request = $this->editable($current, $id);
                if ($request->lock_version !== $result->version) {
                    throw new HelpRequestVersionConflict;
                }

                return $request->load(['author:id,handle', 'technologies' => fn ($query) => $query->orderBy('slug')->orderBy('technologies.id')]);
            });
    }

    private function editable(User $actor, string $id): HelpRequest
    {
        $request = HelpRequest::whereKey($id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize('update', $request);

        return $request;
    }

    /** @return list<array{id: string, version_label: ?string}> */
    private function technologies(HelpRequest $request): array
    {
        $result = [];
        foreach ($request->technologies()->orderBy('technologies.id')->get() as $technology) {
            $version = $technology->getRelation('pivot')->getAttribute('version_label');
            if ($version !== null && ! is_string($version)) {
                throw new IdempotencyStorageFailed('Version de technologie mémorisée invalide.');
            }
            $result[] = ['id' => $technology->id, 'version_label' => $version];
        }

        return $result;
    }
}
