<?php

namespace Tests\Integration\Lab;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;

/**
 * Scénario B1-05 (lot B35) : course réelle entre deux processus PHP qui
 * soumettent le même (run_id, event_id). L'unicité PostgreSQL doit garantir
 * un seul événement, une seule commande, et exactement un `duplicate=false`
 * côté gagnant et un `duplicate=true` côté perdant.
 *
 * Les enfants commitent hors de la transaction PHPUnit du parent : un nettoyage
 * manuel via la connexion secondaire est obligatoire pour que la base reste
 * cohérente entre les tests. Les deux processus doivent être pompés en même
 * temps : un `wait()` séquentiel n'arrose qu'un seul process à la fois et le
 * second ne lirait `GO` qu'après la sortie du premier, détruisant toute
 * concurrence réelle.
 */
final class ConcurrentTestEventTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_b1_05_concurrent_submissions_resolve_to_a_single_order(): void
    {
        /** @var array<string, mixed> $configuration */
        $configuration = config('database.connections.pgsql');
        config(['database.connections.test_events_cleanup' => $configuration]);

        $runId = (string) Str::uuid();
        $eventId = 'evt_concurrent_'.Str::random(8);
        $orderRef = 'ord_concurrent_'.Str::random(8);

        $environment = [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => '',
            'DB_HOST' => (string) $configuration['host'],
            'DB_PORT' => (string) $configuration['port'],
            'DB_DATABASE' => (string) $configuration['database'],
            'DB_USERNAME' => (string) $configuration['username'],
            'DB_PASSWORD' => (string) $configuration['password'],
            'LAB_RUN_ID' => $runId,
            'LAB_EVENT_ID' => $eventId,
            'LAB_ORDER_REF' => $orderRef,
            'LAB_AMOUNT_MINOR' => '2599',
            'LAB_CURRENCY' => 'EUR',
        ];

        $streams = [new InputStream, new InputStream];
        /** @var array<int, Process> $processes */
        $processes = [];

        // Trigger pg_sleep(0.3) restreint à ce run_id, posé via la connexion
        // secondaire pour forcer un chevauchement reproductible entre les deux
        // soumissions concurrentes. Sans ce ralentisseur, l'ordonnancement
        // Windows peut sérialiser suffisamment les deux processus pour qu'une
        // mutation naïve passe plus d'une fois sur vingt. Le trigger est
        // supprimé dans le finally.
        $cleanup = DB::connection('test_events_cleanup');
        $cleanup->statement("CREATE OR REPLACE FUNCTION pg_temp_b35_slow() RETURNS trigger AS \$\$ BEGIN IF NEW.run_id = '$runId' THEN PERFORM pg_sleep(0.3); END IF; RETURN NEW; END; \$\$ LANGUAGE plpgsql");
        $cleanup->statement('DROP TRIGGER IF EXISTS b35_slow_trigger ON test_events');
        $cleanup->statement('CREATE TRIGGER b35_slow_trigger AFTER INSERT ON test_events FOR EACH ROW EXECUTE FUNCTION pg_temp_b35_slow()');

        try {
            foreach ($streams as $stream) {
                $process = new Process(
                    [PHP_BINARY, base_path('tests/Fixtures/process-test-event-concurrently.php')],
                    base_path(),
                    $environment,
                    $stream,
                    30,
                );
                $process->start();
                $processes[] = $process;
            }

            foreach ($processes as $process) {
                while (! str_contains($process->getOutput(), 'READY') && $process->isRunning()) {
                    $process->checkTimeout();
                    usleep(10_000);
                }
                $this->assertStringContainsString('READY', $process->getOutput(), $process->getErrorOutput());
            }

            foreach ($streams as $stream) {
                $stream->write("GO\n");
                $stream->close();
            }

            // Pomper les deux processus simultanément : un wait() séquentiel ne
            // pompe que son process, l'autre resterait bloqué en lecture de STDIN.
            // checkTimeout() applique le timeout de chaque Process pendant la boucle.
            while (true) {
                $allDone = true;
                foreach ($processes as $process) {
                    if ($process->isRunning()) {
                        $process->checkTimeout();
                        $process->getIncrementalOutput();
                        $process->getIncrementalErrorOutput();
                        $allDone = false;
                    }
                }
                if ($allDone) {
                    break;
                }
                usleep(10_000);
            }

            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                /** @var array{order_id: string, source_event_id: string, run_id: string, order_ref: string, duplicate: bool} $decoded */
                $decoded = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
                $results[] = $decoded;
            }

            $connection = DB::connection('test_events_cleanup');
            $this->assertSame(
                1,
                $connection->table('test_events')->where('run_id', $runId)->count(),
                'Un seul événement doit survivre à la course sur (run_id, event_id).',
            );
            $this->assertSame(
                1,
                $connection->table('test_orders')->where('run_id', $runId)->count(),
                'Une seule commande dérivée doit être écrite.',
            );

            $this->assertSame(
                $results[0]['order_id'],
                $results[1]['order_id'],
                'Les deux processus concurrents doivent recevoir exactement le même order_id.',
            );
            $this->assertSame(
                $results[0]['source_event_id'],
                $results[1]['source_event_id'],
                'Les deux commandes rapportées doivent pointer vers le même événement source.',
            );
            $this->assertSame($orderRef, $results[0]['order_ref']);

            // Résultat typé : exactement un gagnant (duplicate=false) et un perdant
            // (duplicate=true). Attention : [false, true] est aussi le résultat d'une
            // exécution strictement séquentielle (le premier crée, le second rejoue),
            // donc cette assertion n'est pas en elle-même une preuve de chevauchement.
            // La preuve de concurrence est la sensibilité du test à une mutation du
            // service en SELECT puis INSERT naïf, mesurée hors suite.
            $duplicates = [$results[0]['duplicate'], $results[1]['duplicate']];
            sort($duplicates);
            $this->assertSame([false, true], $duplicates, 'Les deux soumissions doivent produire exactement un duplicate=false et un duplicate=true.');
        } finally {
            foreach ($streams as $stream) {
                $stream->close();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }

            // Nettoyage via la connexion secondaire : les enfants ont commité hors
            // de la transaction PHPUnit du parent, RefreshDatabase ne les voit pas.
            $cleanup->statement('DROP TRIGGER IF EXISTS b35_slow_trigger ON test_events');
            $cleanup->statement('DROP FUNCTION IF EXISTS pg_temp_b35_slow()');
            DB::connection('test_events_cleanup')
                ->table('test_orders')
                ->where('run_id', $runId)
                ->delete();
            DB::connection('test_events_cleanup')
                ->table('test_events')
                ->where('run_id', $runId)
                ->delete();
            DB::purge('test_events_cleanup');
        }
    }
}
