<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\VerificationCases\VerificationCaseBindingRejected;
use App\Services\VerificationCases\BindCaseIntegrationService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

final class BindCaseIntegration extends Command
{
    protected $signature = 'lab:bind-case {manifest : Chemin absolu vers le manifeste de release}';

    protected $description = 'Lier des révisions de cas revues à un scénario approuvé via un manifeste de release. Aucun run.';

    public function handle(BindCaseIntegrationService $service): int
    {
        $manifest = (string) $this->argument('manifest');
        if ($manifest === '') {
            $this->error('Chemin de manifeste manquant.');

            return self::FAILURE;
        }
        try {
            $result = $service->bindFromManifest($manifest);
        } catch (VerificationCaseBindingRejected $exception) {
            $this->error('Intégration refusée : '.$exception->getMessage());

            return self::FAILURE;
        } catch (QueryException) {
            $this->error('Intégration refusée : stockage applicatif indisponible.');

            return self::FAILURE;
        }
        $this->info('Révisions intégrées : '.$result['inserted'].' ; déjà intégrées : '.$result['skipped'].'.');

        return self::SUCCESS;
    }
}
