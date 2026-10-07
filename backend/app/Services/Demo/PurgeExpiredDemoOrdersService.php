<?php

namespace App\Services\Demo;

use App\Models\Demo\DemoConnection;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Purge B2 born?e ? 1000 commandes, sous le m?me verrou que les enregistrements ; aucune intention vivante supprim?e. */
final class PurgeExpiredDemoOrdersService
{
    /**
     * Rétention par défaut en secondes (24 heures).
     */
    public const DEFAULT_RETENTION_SECONDS = 86_400;

    /**
     * Garde-fou maximum (30 jours). Au-delà, la commande refuse plutôt que
     * d'opérer sur une fenêtre glissante aberrante.
     */
    public const MAXIMUM_RETENTION_SECONDS = 2_592_000;

    /**
     * Taille d'un lot de suppression. Même ordre de grandeur que
     * `IdempotencyService::pruneExpired()`.
     */
    public const BATCH_SIZE = 1_000;

    public function purge(DateTimeImmutable $cutoff): int
    {
        return DB::connection(DemoConnection::NAME)->transaction(function () use ($cutoff): int {
            $connection = DB::connection(DemoConnection::NAME);
            $connection->select('SELECT pg_advisory_xact_lock(238038)');
            $ids = $connection->table('demo_orders')
                ->where('created_at', '<', $cutoff->format('Y-m-d H:i:s.uP'))
                ->where('expires_at', '<=', now())
                ->orderBy('created_at')->orderBy('id')->limit(self::BATCH_SIZE)->pluck('id');

            return $connection->table('demo_orders')->whereIn('id', $ids)->delete();
        });
    }

    /**
     * Convertit un délai en secondes en date de coupure UTC, en validant la
     * borne. Lève une InvalidArgumentException pour toute valeur hors du
     * domaine autorisé ; cette exception n'est pas convertie en 4xx (la
     * commande est interne, pas une route HTTP).
     */
    public function cutoffFor(DateTimeImmutable $now, int $retentionSeconds): DateTimeImmutable
    {
        if ($retentionSeconds < 1 || $retentionSeconds > self::MAXIMUM_RETENTION_SECONDS) {
            throw new InvalidArgumentException('Rétention B2 hors des bornes autorisées (1 seconde à 30 jours).');
        }

        return $now->modify('-'.$retentionSeconds.' seconds');
    }
}
