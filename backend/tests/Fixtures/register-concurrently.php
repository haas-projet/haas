<?php

use App\Data\Identity\RegisterMemberData;
use App\Exceptions\Identity\RegistrationRejected;
use App\Models\User;
use App\Services\Identity\RegisterMemberService;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
config(['registration.terms_version' => 'fixture-concurrency-v1']);

// Les deux processus ont haché leur mot de passe et attendent avant le premier INSERT.
User::creating(function (): void {
    echo "READY\n";
    flush();
    if (trim((string) fgets(STDIN)) !== 'GO') {
        throw new RuntimeException('Barrière de test interrompue.');
    }
});

try {
    $user = $app->make(RegisterMemberService::class)->register(new RegisterMemberData(
        'MembreConcurrent', 'concurrent@example.test', 'mot-de-passe-de-test', 'fixture-concurrency-v1',
    ));
    echo json_encode(['result' => 'created', 'id' => $user->id], JSON_THROW_ON_ERROR)."\n";
} catch (RegistrationRejected) {
    echo "{\"result\":\"duplicate\"}\n";
}
