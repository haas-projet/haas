<?php

declare(strict_types=1);

namespace App\Services\Lab;

use App\Models\Lab\LabDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class WriteLabDefinitionAudit
{
    public function bound(LabDefinition $definition): void
    {
        $database = DB::connection('pgsql');
        if ($database->getDriverName() !== 'pgsql' || $database->transactionLevel() < 1
            || ! $definition->exists || $definition->getConnection() !== $database) {
            throw new LogicException('Audit de liaison requis dans sa transaction applicative.');
        }
        $database->table('content_revisions')->insert([
            'id' => (string) Str::uuid(), 'actor_id' => null, 'resource_type' => 'lab_definition',
            'resource_id' => $definition->id, 'revision' => 1, 'action' => 'lab.definition.bound',
            'metadata' => json_encode(['version_id' => $definition->version_id, 'release' => $definition->release], JSON_THROW_ON_ERROR),
            'occurred_at' => now()->utc(),
        ]);
    }
}
