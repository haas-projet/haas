<?php

namespace App\Services\Identity;

use App\Data\Identity\VerifyEmailData;
use App\Exceptions\Identity\AccountMailStorageFailed;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class VerifyEmailService
{
    public function verify(User $actor, VerifyEmailData $data): User
    {
        try {
            return DB::transaction(function () use ($actor, $data): User {
                $user = User::whereKey($data->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('manageAccountMail', $user);
                if (! hash_equals(sha1($user->getEmailForVerification()), $data->emailHash)) {
                    throw new AuthorizationException;
                }
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                    DB::afterCommit(static fn () => event(new Verified($user)));
                }

                return $user;
            });
        } catch (QueryException $exception) {
            throw new AccountMailStorageFailed('Échec du stockage de la vérification ; SQLSTATE '.$exception->getCode().'.');
        }
    }
}
