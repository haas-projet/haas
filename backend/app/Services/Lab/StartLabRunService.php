<?php

declare(strict_types=1);

namespace App\Services\Lab;

use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\Lab\LabSelectionData;
use App\Data\Lab\QueuedLabRun;
use App\Data\Lab\StartLabRunData;
use App\Enums\Lab\LabRunState;
use App\Exceptions\Lab\LabCapacityReached;
use App\Exceptions\Lab\LabQuotaExceeded;
use App\Infrastructure\Lab\ApprovedLabRegistry;
use App\Models\Lab\LabRun;
use App\Models\User;
use App\Queries\Lab\LabDefinitionQuery;
use App\Services\Idempotency\IdempotencyService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lancement de laboratoire B34 : réserve atomiquement une place dans la
 * fenêtre horaire glissante du membre et dans la capacité globale, puis
 * persiste un lab_run queued. L'orchestration réelle (worker, assertions)
 * reste à B36 ; ici aucun processus n'est démarré, aucune ligne de résultat
 * n'est écrite.
 */
final class StartLabRunService
{
    public const ROUTE_TARGET = 'POST /api/v1/lab-runs';

    private const ADVISORY_LOCK = 554478; // b34_lab_capacity

    /** Nombre d'unités consommées par un lancement simple ; BV206 pourra en demander deux. */
    public const UNITS_PER_SIMPLE_RUN = 1;

    public function __construct(
        private readonly LabDefinitionQuery $definitions,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function handle(User $actor, StartLabRunData $data, string $idempotencyKey): QueuedLabRun
    {
        $selection = new LabSelectionData($data->payload());
        $key = new IdempotencyKey($idempotencyKey);
        $idempotencyData = new IdempotencyData(self::ROUTE_TARGET, $key, $data->payload());

        /** @var QueuedLabRun $queued */
        $queued = $this->idempotency->execute(
            actor: $actor,
            data: $idempotencyData,
            authorize: static function (): void {
                // La porte « member » (verified + active) est posée par les
                // middlewares auth:sanctum, verified et EnsureAccountIsActive.
                // Pas d'autorisation métier supplémentaire ici : le registre et
                // la visibilité de la version sont contrôlés sous verrou.
            },
            operation: function (User $current) use ($selection): StoredCommandResult {
                return $this->reserveAndQueue($current, $selection);
            },
            resolve: function (User $current, StoredCommandResult $stored): QueuedLabRun {
                $runId = $stored->references['run'] ?? throw new ModelNotFoundException;
                $run = LabRun::query()->whereKey($runId)->where('user_id', $current->id)->firstOr(
                    static fn (): never => throw (new ModelNotFoundException)->setModel(LabRun::class),
                );

                return $this->project($run);
            },
        );

        return $queued;
    }

    private function reserveAndQueue(User $actor, LabSelectionData $selection): StoredCommandResult
    {
        // Verrou avisé transactionnel : sérialise la décision de réservation
        // pour TOUS les membres. L'index unique « un seul running » et la
        // contrainte unique (user_id,request_key) de B33 restent la ceinture ;
        // ce verrou protège aussi la file et la fenêtre glissante.
        DB::connection('pgsql')->select('SELECT pg_advisory_xact_lock(?)', [self::ADVISORY_LOCK]);

        $definition = $this->definitions->selection($selection);
        $now = CarbonImmutable::now('UTC');
        $hourAgo = $now->subHour();

        // Fenêtre horaire glissante par membre : lecture restrictive. Les
        // runs rejoués (même user_id + même request_key) ne génèrent pas de
        // nouvelle ligne (contrainte unique B33) ; ils ne comptent donc
        // jamais deux fois ici.
        $usedUnits = (int) DB::connection('pgsql')->table('lab_runs')
            ->where('user_id', $actor->id)
            ->where('created_at', '>', $hourAgo)
            ->count();
        $limit = $this->hourlyUnits();
        if ($usedUnits + self::UNITS_PER_SIMPLE_RUN > $limit) {
            $oldest = DB::connection('pgsql')->table('lab_runs')
                ->where('user_id', $actor->id)->where('created_at', '>', $hourAgo)
                ->min('created_at');
            $retry = max(1, (int) CarbonImmutable::parse((string) $oldest)->addHour()->diffInSeconds($now, true));
            throw new LabQuotaExceeded(retryAfterSeconds: $retry);
        }

        // Capacité globale : queued ET running comptent comme actifs. Lecture
        // restrictive consignée en question ouverte : B34 interdit un
        // deuxième démarrage tant que la file n'est pas vidée, pour que
        // comparateur et runs simples partagent la même place.
        $maxRunning = $this->maxRunning();
        $active = (int) DB::connection('pgsql')->table('lab_runs')
            ->whereIn('state', ['queued', 'running'])->count();
        if ($active >= $maxRunning) {
            throw new LabCapacityReached(retryAfterSeconds: 30);
        }
        $queueLimit = $this->queueLimit();
        if ($active >= $queueLimit) {
            throw new LabCapacityReached(retryAfterSeconds: 30);
        }

        $runId = (string) Str::uuid();
        $requestKey = (string) Str::uuid();
        DB::connection('pgsql')->table('lab_runs')->insert([
            'id' => $runId,
            'definition_id' => $definition->id,
            'version_id' => $definition->versionId,
            'user_id' => $actor->id,
            'request_key' => $requestKey,
            'scenario' => $selection->scenario->value,
            'release' => $definition->release,
            'module_version' => $definition->moduleVersion,
            'source_commit' => (new ApprovedLabRegistry)->manifest()['source_commit'],
            'code_digest' => $definition->codeDigest,
            'suite_digest' => $definition->suiteDigest,
            'version_digest' => $definition->versionDigest,
            'state' => LabRunState::Queued->value,
            'created_at' => $now,
        ]);

        return new StoredCommandResult(status: 202, references: ['run' => $runId]);
    }

    private function project(LabRun $run): QueuedLabRun
    {
        return new QueuedLabRun(
            id: (string) $run->getAttribute('id'),
            versionId: (string) $run->getAttribute('version_id'),
            state: $run->state,
            scenario: (string) $run->getAttribute('scenario'),
            createdAt: CarbonImmutable::parse((string) $run->getAttribute('created_at'))->utc()->toIso8601ZuluString(),
        );
    }

    public function show(User $actor, string $runId): QueuedLabRun
    {
        if (! Str::isUuid($runId)) {
            throw (new ModelNotFoundException)->setModel(LabRun::class);
        }
        $run = LabRun::query()->whereKey($runId)->where('user_id', $actor->id)->firstOr(
            static fn (): never => throw (new ModelNotFoundException)->setModel(LabRun::class),
        );

        return $this->project($run);
    }

    private function hourlyUnits(): int
    {
        $value = (int) config('lab.hourly_units', 5);

        return max(1, $value);
    }

    private function maxRunning(): int
    {
        $value = (int) config('lab.max_running', 1);

        return max(1, $value);
    }

    private function queueLimit(): int
    {
        $value = (int) config('lab.queue_limit', 20);

        return max(1, $value);
    }
}
