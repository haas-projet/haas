<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Data\Capsules\ReuseReportData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Capsules\ReuseReport;
use App\Models\Capsules\ReuseReportRevision;
use App\Models\User;
use App\Policies\ReuseReportPolicy;
use App\Services\Idempotency\IdempotencyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * B28 — Enregistre ou met à jour un retour humain pour une version publiée.
 *
 * - Un retour actif par (membre, version). Un second POST produit une
 *   révision ; l'historique est append-only (`reuse_report_revisions`).
 * - `is_internal` est calculé par le serveur (owner ou contributeur de
 *   la version). Aucun client ne peut forcer cette valeur.
 * - Idempotency-Key via `IdempotencyService` : deux POST avec la même
 *   clé et la même charge rendent le même retour (ou la même révision).
 */
final class RecordReuseReportService
{
    public function __construct(
        private readonly ReuseReportPolicy $policy,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function handle(User $actor, Capsule $capsule, CapsuleVersion $version, ReuseReportData $data, IdempotencyKey $key): ReuseReport
    {
        return $this->idempotency->execute($actor,
            new IdempotencyData(
                'POST /api/v1/capsules/'.$capsule->id.'/versions/'.$version->id.'/reuse-reports',
                $key,
                [
                    'environment' => $data->environment,
                    'reported_at' => $data->reportedAt->format('Y-m-d'),
                    'outcome' => $data->outcome->value,
                    'observation' => $data->observation,
                ],
            ),
            function (User $current) use ($capsule, $version): void {
                $sourceCapsule = Capsule::whereKey($capsule->id)->firstOrFail();
                $target = CapsuleVersion::whereKey($version->id)->where('capsule_id', $sourceCapsule->id)->firstOrFail();
                if (! $this->policy->record($current, $sourceCapsule, $target)) {
                    throw new AuthorizationException;
                }
            },
            function (User $current) use ($capsule, $version, $data): StoredCommandResult {
                $sourceCapsule = Capsule::whereKey($capsule->id)->firstOrFail();
                $target = CapsuleVersion::whereKey($version->id)->where('capsule_id', $sourceCapsule->id)->firstOrFail();
                if (! $this->policy->record($current, $sourceCapsule, $target)) {
                    throw new AuthorizationException;
                }

                $internal = $sourceCapsule->owner_id === $current->id
                    || DB::table('capsule_contributors')->where('version_id', $target->id)->where('user_id', $current->id)->exists();

                $report = ReuseReport::query()
                    ->where('user_id', $current->id)
                    ->where('version_id', $target->id)
                    ->lockForUpdate()
                    ->first();

                $now = now()->utc();
                if ($report === null) {
                    $report = new ReuseReport;
                    $report->forceFill([
                        'id' => (string) Str::uuid(),
                        'user_id' => $current->id,
                        'version_id' => $target->id,
                        'environment' => $data->environment,
                        'reported_at' => $data->reportedAt,
                        'outcome' => $data->outcome,
                        'observation' => $data->observation,
                        'is_internal' => $internal,
                        'revision' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->save();
                    $status = 201;
                } else {
                    (new ReuseReportRevision)->forceFill([
                        'id' => (string) Str::uuid(),
                        'report_id' => $report->id,
                        'revision' => (int) $report->revision,
                        'environment' => $report->environment,
                        'reported_at' => $report->reported_at,
                        'outcome' => $report->outcome,
                        'observation' => $report->observation,
                        'is_internal' => $report->is_internal,
                        'revised_at' => $now,
                    ])->save();
                    $report->forceFill([
                        'environment' => $data->environment,
                        'reported_at' => $data->reportedAt,
                        'outcome' => $data->outcome,
                        'observation' => $data->observation,
                        'is_internal' => $internal,
                        'revision' => (int) $report->revision + 1,
                        'updated_at' => $now,
                    ])->save();
                    $status = 200;
                }

                return new StoredCommandResult($status, ['report_id' => $report->id], (int) $report->revision);
            }, function (User $current, StoredCommandResult $stored): ReuseReport {
                return ReuseReport::whereKey($stored->references['report_id'])->firstOrFail();
            });
    }
}
