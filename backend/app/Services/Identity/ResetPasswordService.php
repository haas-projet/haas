<?php

namespace App\Services\Identity;

use App\Data\Identity\ResetPasswordData;
use App\Enums\Identity\AccountStatus;
use App\Exceptions\Identity\AccountMailStorageFailed;
use App\Exceptions\Identity\InvalidResetToken;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use SensitiveParameter;

final class ResetPasswordService
{
    public function reset(#[SensitiveParameter] ResetPasswordData $data): void
    {
        try {
            DB::transaction(function () use ($data): void {
                $user = User::where('email', $data->email)->lockForUpdate()->first();
                if ($user === null || $user->status !== AccountStatus::Active) {
                    throw new InvalidResetToken;
                }
                $status = Password::broker('users')->reset([
                    'email' => $data->email, 'token' => $data->token->getValue(), 'password' => $data->password->getValue(),
                ], function (User $member, #[SensitiveParameter] string $password): void {
                    $member->password = Hash::make($password);
                    $member->setRememberToken(Str::random(60));
                    $member->save();
                    DB::table('sessions')->where('user_id', $member->id)->delete();
                    DB::afterCommit(static fn () => event(new PasswordReset($member)));
                });
                if ($status !== Password::PASSWORD_RESET) {
                    throw new InvalidResetToken;
                }
            });
        } catch (QueryException $exception) {
            throw new AccountMailStorageFailed('Échec du stockage de la réinitialisation ; SQLSTATE '.$exception->getCode().'.');
        }
    }
}
