<?php

use App\Data\Identity\ResetPasswordData;
use App\Exceptions\Identity\InvalidResetToken;
use App\Services\Identity\ResetPasswordService;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    app(ResetPasswordService::class)->reset(new ResetPasswordData($input['email'], $input['token'], $input['password']));
    echo "RESET\n";
} catch (InvalidResetToken) {
    echo "INVALID\n";
}
