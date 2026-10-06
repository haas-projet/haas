<?php

namespace App\Services\Demo;

use App\Models\Demo\DemoConnection;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Purge des commandes fictives B2 (lot B38) écrites avant une date de coupure.
 *
 * Portée stricte : la méthode `purge()` n'opère que sur la table `demo_orders`
 * via la connexion `DemoConnection::NAME` et ne consulte aucune autre ressource.
 * Aucune donnée métier HAAS n'est lue, écrite ou supprimée. Le nombre de lignes
 * retirées est renvoyé pour la seule télémétrie serveur ; aucune ligne de
 * contenu ne quitte le service.
 *
 * Rétention par défaut : 24 heures. Elle n'est pas fixée dans le cahier pour
 * `demo_orders` ; la valeur reprend la convention de B13
 * (`docs/product/CAHIER_DES_CHARGES.md:880` « Conservation proposée : 24 heures »)
 * et la règle §15 (« Purge après le test ou par nettoyage de secours sous
 * 24 heures »). À arbitrer par le relecteur ; voir
 * `docs/quality/B38_B2_API.md`.
 *
 * Suppression bornée à 1000 lignes par invocation, comme `IdempotencyService::pruneExpired()`
 * (`backend/app/Services/Idempotency/IdempotencyService.php:75`) : la commande
 * Artisan peut être rejouée pour drainer un retard sans tenir une transaction
 * longue.
 */
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
        return DB::connection(DemoConnection::NAME)
            ->table('demo_orders')
            ->where('created_at', '<', $cutoff->format('Y-m-d H:i:s.uP'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->delete();
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
