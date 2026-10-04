<?php

namespace App\Services\HelpRequests;

use App\Data\HelpRequests\CreateHelpRequestData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\HelpRequests\SubmissionMode;
use App\Exceptions\HelpRequests\HelpRequestCreationRejected;
use App\Exceptions\HelpRequests\HelpRequestVersionConflict;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Models\HelpRequest;
use App\Models\Technology;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Idempotency\IdempotencyService;
use Illuminate\Support\Facades\Gate;

final class CreateHelpRequestService
{
    public function __construct(private readonly IdempotencyService $idempotency, private readonly AuditWriter $audit) {}

    public function create(User $actor, CreateHelpRequestData $data, IdempotencyKey $key): HelpRequest
    {
        return $this->idempotency->execute($actor, new IdempotencyData('POST /api/v1/requests', $key, $data->payload()),
            function (User $current): void {
                Gate::forUser($current)->authorize('create', HelpRequest::class);
            },
            function (User $current) use ($data): StoredCommandResult {
                $ids = array_column($data->technologies, 'id');
                $foundIds = Technology::whereIn('id', $ids)->orderBy('id')->sharedLock()->pluck('id')->all();
                if (count($foundIds) !== count($ids)) {
                    throw new HelpRequestCreationRejected;
                }
                $request = new HelpRequest($data->content);
                $request->author_id = $current->id;
                $request->help_intent = $data->helpIntent;
                $request->state = $data->mode === SubmissionMode::Draft ? HelpRequestState::Draft : HelpRequestState::Open;
                $request->save();
                $pivot = [];
                foreach ($data->technologies as $technology) {
                    $pivot[$technology['id']] = ['version_label' => $technology['version_label']];
                }
                $request->technologies()->attach($pivot);
                $this->audit->helpRequestCreated($current, $request);

                return new StoredCommandResult(201, ['request_id' => $request->id], $request->lock_version);
            },
            function (User $current, StoredCommandResult $result): HelpRequest {
                if ($result->status !== 201 || array_keys($result->references) !== ['request_id']) {
                    throw new IdempotencyStorageFailed('Résultat de demande mémorisé invalide.');
                }
                $request = HelpRequest::whereKey($result->references['request_id'])->lockForUpdate()->firstOrFail();
                Gate::forUser($current)->authorize('view', $request);
                if ($request->author_id !== $current->id || $request->lock_version !== $result->version) {
                    throw new HelpRequestVersionConflict;
                }

                return $request->load(['author:id,handle', 'technologies' => fn ($query) => $query->orderBy('slug')->orderBy('technologies.id')]);
            });
    }
}
