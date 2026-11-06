<?php

declare(strict_types=1);

namespace App\Infrastructure\Lab;

use App\Exceptions\Lab\LabCapacityReached;
use App\Exceptions\Lab\LabQuotaExceeded;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * BV205 — extrait de StartLabRunService (B34) : verrou avisé transactionnel,
 * comptage global des emplacements actifs et fenêtre horaire glissante par
 * membre. Même valeur de verrou, mêmes bornes (`lab.max_running`,
 * `lab.queue_limit`, `lab.hourly_units`) et mêmes exceptions ; le comportement
 * observable de StartLabRunService est inchangé. BV206 remplacera le COUNT(*)
 * de activeSlotCount() par un comptage « un emplacement par comparaison »
 * sans toucher à cette extraction.
 */
final class LabCapacityGuard
{
    public const ADVISORY_LOCK = 554478; // b34_lab_capacity

    /** Verrou avisé transactionnel partagé par tous les lanceurs (simples et comparaisons). */
    public function acquireAdvisoryLock(): void
    {
        DB::connection('pgsql')->select('SELECT pg_advisory_xact_lock(?)', [self::ADVISORY_LOCK]);
    }

    /**
     * Refuse si la réservation de `$units` unités dépasserait le quota horaire
     * glissant du membre ou la capacité globale. Les décomptes se font sous le
     * verrou avisé (acquireAdvisoryLock) pour que les lanceurs partagent une
     * décision atomique. Jamais exécuté hors transaction.
     */
    public function assertRoomForUnits(User $actor, int $units): void
    {
        if ($units < 1) {
            throw new \InvalidArgumentException('Unités à réserver < 1.');
        }
        $now = CarbonImmutable::now('UTC');
        $hourAgo = $now->subHour();

        $usedUnits = (int) DB::connection('pgsql')->table('lab_runs')
            ->where('user_id', $actor->id)
            ->where('created_at', '>', $hourAgo)
            ->count();
        $hourlyLimit = $this->hourlyUnits();
        if ($usedUnits + $units > $hourlyLimit) {
            $oldest = DB::connection('pgsql')->table('lab_runs')
                ->where('user_id', $actor->id)
                ->where('created_at', '>', $hourAgo)
                ->min('created_at');
            $retry = max(1, (int) CarbonImmutable::parse((string) $oldest)->addHour()->diffInSeconds($now, true));
            throw new LabQuotaExceeded(retryAfterSeconds: $retry);
        }

        $active = $this->activeSlotCount();
        $maxRunning = $this->maxRunning();
        if ($active >= $maxRunning) {
            throw new LabCapacityReached(retryAfterSeconds: 30);
        }
        $queueLimit = $this->queueLimit();
        if ($active >= $queueLimit) {
            throw new LabCapacityReached(retryAfterSeconds: 30);
        }
    }

    /**
     * Nombre d'emplacements actifs (queued + running). Un run isolé compte
     * pour 1 emplacement (comparison_id est NULL donc COALESCE renvoie
     * l'identifiant de la ligne, unique) ; une comparaison compte pour 1
     * emplacement partagé par ses deux enfants (ils partagent le même
     * comparison_id). Décision du propriétaire du domaine BV206 (CAHIER
     * §44 ligne 1651 : « un seul run réellement actif au maximum
     * globalement ; les deux enfants sont séquentiels »).
     */
    public function activeSlotCount(): int
    {
        /** @var object{total:int} $row */
        $row = DB::connection('pgsql')
            ->select(<<<'SQL'
                SELECT COUNT(DISTINCT COALESCE(comparison_id::text, id::text))::int AS total
                FROM lab_runs
                WHERE state IN ('queued','running')
            SQL)[0];

        return (int) $row->total;
    }

    public function hourlyUnits(): int
    {
        $value = (int) config('lab.hourly_units', 5);

        return max(1, $value);
    }

    public function maxRunning(): int
    {
        $value = (int) config('lab.max_running', 1);

        return max(1, $value);
    }

    public function queueLimit(): int
    {
        $value = (int) config('lab.queue_limit', 20);

        return max(1, $value);
    }
}
