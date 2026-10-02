<?php

namespace App\Services\Identity;

use App\Data\Identity\ForgotPasswordData;
use App\Exceptions\Identity\AccountMailStorageFailed;
use App\Models\User;
use App\Support\Identity\AccountMailConfiguration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

final class RequestPasswordResetService
{
    public function request(ForgotPasswordData $data): void
    {
        app(AccountMailConfiguration::class)->validate();
        try {
            DB::transaction(function () use ($data): void {
                // Sérialise émission et consommation ; le broker conserve ses TTL/throttle Laravel.
                User::where('email', $data->email)->lockForUpdate()->first();
                Password::broker('users')->sendResetLink(['email' => $data->email, 'status' => 'active']);
                // Même sortie publique pour compte absent, suspendu, lien récent ou nouvel envoi.
            });
        } catch (QueryException $exception) {
            throw new AccountMailStorageFailed('Échec du stockage du courriel de compte ; SQLSTATE '.$exception->getCode().'.');
        }
    }
}
