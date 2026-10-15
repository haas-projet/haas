<?php

declare(strict_types=1);

namespace App\Services\VerificationCases;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use LogicException;
use RuntimeException;

// Audit du domaine cas : insertion directe dans content_revisions (B12), dans
// la transaction du service appelant. Même approche que WriteCapsuleAudit.
final class WriteVerificationCaseAudit
{
    /** @param array<string, int|string|list<string>|null> $metadata */
    public function caseEvent(User $actor, string $caseId, string $action, array $metadata): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'pgsql' || $connection->transactionLevel() < 1
            || $actor->getConnection() !== $connection || ! $actor->exists) {
            throw new LogicException('Audit cas requis dans la transaction PostgreSQL métier.');
        }
        try {
            $revision = (int) DB::table('content_revisions')
                ->where('resource_type', 'verification_case')
                ->where('resource_id', $caseId)
                ->max('revision') + 1;
            DB::table('content_revisions')->insert([
                'id' => (string) Str::uuid(),
                'actor_id' => $actor->id,
                'resource_type' => 'verification_case',
                'resource_id' => $caseId,
                'revision' => $revision,
                'action' => $action,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'occurred_at' => now()->utc(),
            ]);
        } catch (JsonException $e) {
            throw new RuntimeException('Métadonnées d\'audit non sérialisables.', 0, $e);
        }
    }
}
