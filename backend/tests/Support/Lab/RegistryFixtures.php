<?php

declare(strict_types=1);

namespace Tests\Support\Lab;

use App\Infrastructure\Lab\ApprovedLabRegistry;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\HelpRequest;
use App\Models\Lab\LabDefinition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Control fixtures only: these records are not evidence of an execution. */
trait RegistryFixtures
{
    private function publishedVersion(?HelpRequest $source = null, ?Capsule $capsule = null): CapsuleVersion
    {
        $owner = User::factory()->verified()->create();
        $capsule ??= $source === null
            ? Capsule::factory()->create(['owner_id' => $owner->id])
            : Capsule::factory()->fromResolvedRequest($source->id)->create(['owner_id' => $owner->id]);

        return CapsuleVersion::factory()->published(User::factory()->verified()->moderator()->create())->create([
            'capsule_id' => $capsule->id,
            'version_label' => $capsule->versions()->count().'.0.0',
            'content_digest' => hash('sha256', 'B33 fixture '.$capsule->id),
        ]);
    }

    /** @param array<string, mixed> $changes */
    private function definition(CapsuleVersion $version, array $changes = []): string
    {
        $id = (string) Str::uuid();
        DB::connection('pgsql')->table('lab_definitions')->insert(array_replace([
            'id' => $id, 'version_id' => $version->id,
            ...(new ApprovedLabRegistry)->manifest(),
            'version_digest' => $version->content_digest ?? str_repeat('a', 64),
            'enabled' => true, 'created_at' => now()->utc(),
        ], $changes));

        return $id;
    }

    private function queuedRun(string $definitionId): string
    {
        $definition = LabDefinition::query()->findOrFail($definitionId);
        $id = (string) Str::uuid();
        DB::connection('pgsql')->table('lab_runs')->insert([
            'id' => $id, 'definition_id' => $definitionId, 'version_id' => $definition->version_id,
            'user_id' => User::factory()->verified()->create()->id, 'request_key' => (string) Str::uuid(),
            'scenario' => 'nominal_event', 'state' => 'queued', 'created_at' => now()->utc(),
            'release' => $definition->release, 'module_version' => $definition->module_version,
            'source_commit' => $definition->source_commit, 'code_digest' => $definition->code_digest,
            'suite_digest' => $definition->suite_digest, 'version_digest' => $definition->version_digest,
        ]);

        return $id;
    }
}
