<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;

final class RegistrationConcurrencyTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_two_simultaneous_registrations_create_exactly_one_complete_account(): void
    {
        $configuration = config('database.connections.pgsql');
        config(['database.connections.registration_cleanup' => $configuration]);
        $environment = [
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'MAIL_MAILER' => 'array',
            'DB_HOST' => $configuration['host'], 'DB_PORT' => (string) $configuration['port'],
            'DB_DATABASE' => $configuration['database'], 'DB_USERNAME' => $configuration['username'],
            'DB_PASSWORD' => $configuration['password'],
        ];
        $streams = [new InputStream, new InputStream];
        $processes = [];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/register-concurrently.php')], base_path(), $environment, $stream, 30);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                while (! str_contains($process->getOutput(), 'READY') && $process->isRunning()) {
                    $process->checkTimeout();
                    usleep(10000);
                }
                $this->assertStringContainsString('READY', $process->getOutput(), $process->getErrorOutput());
            }
            foreach ($streams as $stream) {
                $stream->write("GO\n");
                $stream->close();
            }
            foreach ($processes as $process) {
                // Pomper les deux entrées avant d'attendre la fin d'un seul enfant.
                $process->isRunning();
            }
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            $this->assertEqualsCanonicalizing(['created', 'duplicate'], $results);
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('profiles', 1);
            $this->assertDatabaseCount('user_terms_acceptances', 1);
            $this->assertDatabaseCount('jobs', 1);
        } finally {
            foreach ($streams as $stream) {
                $stream->close();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            // Les enfants ont réellement commité, hors de la transaction PHPUnit du parent.
            DB::connection('registration_cleanup')->table('users')->where('email', 'concurrent@example.test')->delete();
            DB::connection('registration_cleanup')->table('jobs')->where('queue', 'account-mail')->delete();
            DB::purge('registration_cleanup');
        }
    }
}
