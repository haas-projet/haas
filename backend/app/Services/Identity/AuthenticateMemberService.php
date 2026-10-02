<?php

namespace App\Services\Identity;

use App\Data\Identity\LoginData;
use App\Enums\Identity\AccountStatus;
use App\Exceptions\Identity\AuthenticationStorageFailed;
use App\Exceptions\Identity\InactiveAccount;
use App\Exceptions\Identity\InvalidCredentials;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use SensitiveParameter;

final class AuthenticateMemberService
{
    public function __construct(private readonly Timebox $timebox) {}

    public function authenticate(#[SensitiveParameter] LoginData $data): User
    {
        try {
            return $this->timebox->call(fn (): User => DB::transaction(function () use ($data): User {
                $user = User::where('email', $data->email)->lockForUpdate()->first();
                // Les anciens hashes bcrypt passent par une réinitialisation, jamais par un check tronqué.
                if ($user === null || Hash::info($user->password)['algoName'] !== 'argon2id'
                    || ! Hash::check($data->password->getValue(), $user->password)) {
                    throw new InvalidCredentials;
                }
                if ($user->status !== AccountStatus::Active) {
                    throw new InactiveAccount;
                }
                if (Hash::needsRehash($user->password)) {
                    $user->password = Hash::make($data->password->getValue());
                    $user->save();
                }

                return $user;
            }), 200000);
        } catch (QueryException $exception) {
            throw new AuthenticationStorageFailed('Échec du stockage de la connexion ; SQLSTATE '.$exception->getCode().'.');
        }
    }
}
