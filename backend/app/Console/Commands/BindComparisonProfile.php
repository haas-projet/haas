<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Comparisons\ComparisonProfileBindingRejected;
use App\Services\Comparisons\BindComparisonProfileService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

final class BindComparisonProfile extends Command
{
    protected $signature = 'lab:bind-comparison-profile {manifest : Chemin absolu vers le manifeste de release}';

    protected $description = 'Lier un profil de comparaison B1 approuvé à une release via un manifeste. Aucun run.';

    public function handle(BindComparisonProfileService $service): int
    {
        $manifest = (string) $this->argument('manifest');
        if ($manifest === '') {
            $this->error('Chemin de manifeste manquant.');

            return self::FAILURE;
        }
        try {
            $profile = $service->bindFromManifest($manifest);
        } catch (ComparisonProfileBindingRejected $exception) {
            $this->error('Liaison refusée : '.$exception->getMessage());

            return self::FAILURE;
        } catch (QueryException) {
            $this->error('Liaison refusée : stockage applicatif indisponible.');

            return self::FAILURE;
        }
        $this->info('Profil de comparaison lié ; aucun run lancé. (id='.$profile->id.')');

        return self::SUCCESS;
    }
}
