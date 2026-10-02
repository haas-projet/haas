<?php

namespace App\Services\Identity;

use App\Data\Identity\RegisterMemberData;
use App\Exceptions\Identity\RegistrationRejected;
use App\Exceptions\Identity\RegistrationStorageFailed;
use App\Models\Profile;
use App\Models\User;
use App\Support\Identity\RegistrationTerms;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

final class RegisterMemberService
{
    public function __construct(private readonly RegistrationTerms $terms) {}

    public function register(#[SensitiveParameter] RegisterMemberData $data): User
    {
        if ($data->termsVersion !== $this->terms->currentVersion()) {
            throw new RegistrationRejected(['terms_version' => ['Acceptez la version actuelle des conditions d’utilisation.']]);
        }

        // Toujours hacher le texte, même s’il ressemble déjà à un hash reconnu par le cast Eloquent.
        $password = Hash::make($data->password->getValue());

        try {
            return DB::transaction(function () use ($data, $password): User {
                try {
                    $user = User::create(['handle' => $data->handle, 'email' => $data->email, 'password' => $password]);
                } catch (UniqueConstraintViolationException) {
                    // PostgreSQL arbitre aussi deux inscriptions simultanées.
                    throw new RegistrationRejected([
                        'handle' => ['Ce pseudonyme ou ce courriel est déjà utilisé.'],
                        'email' => ['Ce pseudonyme ou ce courriel est déjà utilisé.'],
                    ]);
                }
                $user->profile()->save(new Profile);
                DB::table('user_terms_acceptances')->insert([
                    'user_id' => $user->id,
                    'version' => $data->termsVersion,
                    'accepted_at' => now(),
                ]);
                $user->sendEmailVerificationNotification();

                return $user;
            });
        } catch (QueryException $exception) {
            // Ne pas chaîner une QueryException contenant courriel et hash dans ses bindings.
            throw new RegistrationStorageFailed('Échec du stockage de l’inscription ; SQLSTATE '.$exception->getCode().'.');
        }
    }
}
