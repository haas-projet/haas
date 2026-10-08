<?php

namespace App\Console\Commands;

use App\Services\Demo\PurgeExpiredDemoOrdersService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Commande Artisan de purge des commandes fictives B2 (lot B38).
 *
 * La commande reste mince : la décision métier (fenêtre de rétention, bornes
 * autorisées, limite de lot) vit dans `PurgeExpiredDemoOrdersService`. La
 * sortie n'affiche que le nombre de lignes effectivement retirées ; aucun
 * contenu, identifiant ou empreinte n'est journalisé.
 */
final class PruneDemoOrders extends Command
{
    protected $signature = 'demo:prune {--older-than= : Rétention en secondes ; défaut 86400 (24 h), maximum 2592000 (30 j).}';

    protected $description = 'Retirer au plus 1000 commandes fictives B2 plus anciennes que la rétention, sans contenu dans la sortie.';

    public function handle(PurgeExpiredDemoOrdersService $service): int
    {
        $retentionSeconds = PurgeExpiredDemoOrdersService::DEFAULT_RETENTION_SECONDS;
        $option = $this->option('older-than');
        if ($option !== null) {
            if (! ctype_digit($option)) {
                $this->error('L’option --older-than doit être un entier positif.');

                return self::INVALID;
            }
            $retentionSeconds = (int) $option;
        }

        try {
            $cutoff = $service->cutoffFor(CarbonImmutable::now('UTC'), $retentionSeconds);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $removed = $service->purge($cutoff);
        $this->info($removed.' commande(s) fictive(s) retirée(s).');

        return self::SUCCESS;
    }
}
