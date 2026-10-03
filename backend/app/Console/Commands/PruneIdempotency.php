<?php

namespace App\Console\Commands;

use App\Services\Idempotency\IdempotencyService;
use Illuminate\Console\Command;

final class PruneIdempotency extends Command
{
    protected $signature = 'idempotency:prune';

    protected $description = 'Retirer au plus 1000 intentions API expirées sans afficher leur contenu.';

    public function handle(IdempotencyService $service): int
    {
        $this->info($service->pruneExpired().' intention(s) expirée(s) retirée(s).');

        return self::SUCCESS;
    }
}
