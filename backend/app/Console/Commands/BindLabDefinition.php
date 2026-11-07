<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Lab\LabDefinitionBindingRejected;
use App\Services\Lab\BindLabDefinitionService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class BindLabDefinition extends Command
{
    protected $signature = 'lab:bind-definition {version : UUID de la version publiée} {release : Release de la liste fermée}';

    protected $description = 'Lier une version publiée à une release interne fixe sans lancer le laboratoire.';

    public function handle(BindLabDefinitionService $service): int
    {
        $version = $this->argument('version');
        $release = $this->argument('release');
        try {
            $service->bind($version, $release);
        } catch (ValidationException) {
            $this->error('Liaison refusée : UUID ou release non admis.');

            return self::FAILURE;
        } catch (LabDefinitionBindingRejected $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (QueryException) {
            $this->error('Liaison refusée : stockage applicatif indisponible.');

            return self::FAILURE;
        }
        $this->info('Définition liée à la release fixe ; aucun run lancé.');

        return self::SUCCESS;
    }
}
