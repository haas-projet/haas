<?php

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;

final class PasswordResetConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_two_processes_can_consume_a_reset_token_only_once(): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key, 'auth.defaults.passwords' => 'users']);
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $db = config('database.connections.pgsql');
        $env = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => $key, 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password']];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/reset-password-concurrently.php')], base_path(), $env, $stream, 30);
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
            foreach ($streams as $index => $stream) {
                $stream->write(json_encode(['email' => $user->email, 'token' => $token, 'password' => 'nouveau-mot-de-passe-'.$index], JSON_THROW_ON_ERROR)."\n");
                $stream->close();
            }
            foreach ($processes as $process) {
                $process->isRunning();
            }
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $outcomes[] = end($lines);
            }
            $this->assertEqualsCanonicalizing(['RESET', 'INVALID'], $outcomes);
            $this->assertDatabaseCount('password_reset_tokens', 0);
            $winner = array_search('RESET', $outcomes, true);
            $this->assertTrue(Hash::check('nouveau-mot-de-passe-'.$winner, $user->refresh()->password));
        } finally {
            foreach ($streams as $stream) {
                $stream->close();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }
}
