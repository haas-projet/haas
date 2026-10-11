<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use LogicException;
use RuntimeException;

/**
 * Audit interne du domaine capsules : insertion directe dans la table
 * partagée `content_revisions` (B12), sans toucher AuditWriter (profil).
 * Reste minimal : appelé par les Services capsules à l'intérieur de leur
 * transaction. Le rollback de la transaction annule les deux écritures.
 */
final class WriteCapsuleAudit
{
    /** @param array<string, int|string|list<string>> $metadata */
    public function capsuleVersion(User $actor, string $resourceId, string $action, array $metadata): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'pgsql' || $connection->transactionLevel() < 1
            || $actor->getConnection() !== $connection || ! $actor->exists) {
            throw new LogicException('Audit capsule requis dans la transaction PostgreSQL métier.');
        }
        try {
            $revision = (int) DB::table('content_revisions')
                ->where('resource_type', 'capsule_version')
                ->where('resource_id', $resourceId)
                ->max('revision') + 1;
            DB::table('content_revisions')->insert([
                'id' => (string) Str::uuid(),
                'actor_id' => $actor->id,
                'resource_type' => 'capsule_version',
                'resource_id' => $resourceId,
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
