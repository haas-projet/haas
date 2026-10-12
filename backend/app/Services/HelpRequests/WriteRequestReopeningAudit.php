<?php

namespace App\Services\HelpRequests;

use App\Enums\HelpRequests\HelpRequestState;
use App\Exceptions\Audit\AuditStorageFailed;
use App\Models\HelpRequest;
use App\Models\RequestReopening;
use App\Models\Resolution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;

final class WriteRequestReopeningAudit
{
    public function write(User $actor, HelpRequest $parent, Resolution $resolution, RequestReopening $reopening): void
    {
        if (DB::transactionLevel() < 1 || DB::connection()->getDriverName() !== 'pgsql') {
            throw new LogicException('Audit de réouverture requis dans la transaction PostgreSQL métier.');
        }
        foreach ([$actor, $parent, $resolution, $reopening] as $model) {
            if (! $model->exists || $model->getConnection() !== DB::connection()) {
                throw new LogicException('Audit de réouverture requis sur les modèles persistés de la transaction.');
            }
        }
        Gate::forUser($actor)->authorize('reopen', $parent);
        if ($parent->state !== HelpRequestState::Open || $reopening->reopened_by !== $actor->id
            || $resolution->request_id !== $parent->id || $reopening->request_id !== $parent->id
            || $reopening->resolution_id !== $resolution->id || $resolution->revoked_at === null
            || $parent->lock_version !== $reopening->request_version_after) {
            throw new AuthorizationException;
        }
        try {
            $metadata = json_encode(['reopening_id' => $reopening->id, 'resolution_id' => $resolution->id,
                'request_version_before' => $reopening->request_version_before, 'request_version_after' => $reopening->request_version_after], JSON_THROW_ON_ERROR);
            foreach ([['help_request', $parent->id, 'help_request.reopened'], ['resolution', $resolution->id, 'resolution.revoked']] as [$type, $id, $action]) {
                $revision = (int) DB::table('content_revisions')->where('resource_type', $type)->where('resource_id', $id)->max('revision') + 1;
                DB::table('content_revisions')->insert(['id' => (string) Str::uuid(), 'actor_id' => $actor->id,
                    'resource_type' => $type, 'resource_id' => $id, 'revision' => $revision, 'action' => $action,
                    'metadata' => $metadata, 'occurred_at' => $reopening->reopened_at]);
            }
        } catch (QueryException) {
            throw new AuditStorageFailed('Échec du stockage de la révision.');
        }
    }
}
