<?php

namespace App\Services\Identity;

use App\Exceptions\Identity\AccountMailStorageFailed;
use App\Models\User;
use App\Support\Identity\AccountMailConfiguration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ResendVerificationService
{
    public function resend(User $actor): void
    {
        app(AccountMailConfiguration::class)->validate();
        try {
            DB::transaction(function () use ($actor): void {
                $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('manageAccountMail', $user);
                if (! $user->hasVerifiedEmail()) {
                    $user->sendEmailVerificationNotification();
                }
            });
        } catch (QueryException $exception) {
            throw new AccountMailStorageFailed('Échec du stockage du courriel de vérification ; SQLSTATE '.$exception->getCode().'.');
        }
    }
}
