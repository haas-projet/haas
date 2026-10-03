<?php

namespace App\Notifications\Identity;

use App\Exceptions\Identity\AccountMailStorageFailed;
use App\Models\User;
use App\Support\Identity\AccountMailUrls;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Password;
use LogicException;
use SensitiveParameter;

final class ResetAccountPassword extends AccountMailNotification
{
    public function __construct(string $email, #[SensitiveParameter] private readonly string $token)
    {
        parent::__construct($email, (int) config('auth.passwords.users.expire'));
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        try {
            $broker = Password::broker('users');
            if (! $broker instanceof PasswordBroker) {
                throw new LogicException('Le broker Laravel des comptes est requis.');
            }

            return $this->recipientIsCurrent($notifiable) && $broker->tokenExists($notifiable, $this->token);
        } catch (QueryException $exception) {
            throw new AccountMailStorageFailed('Échec du contrôle du courriel de compte ; SQLSTATE '.$exception->getCode().'.');
        }
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Réinitialisez votre mot de passe HAAS')->greeting('Bonjour,')
            ->line('Une réinitialisation du mot de passe de votre compte a été demandée.')
            ->action('Choisir un nouveau mot de passe', app(AccountMailUrls::class)->reset($notifiable->email, $this->token))
            ->line('Ce lien est à usage unique et expire une heure après la demande.')
            ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.')->salutation('L’équipe HAAS');
    }
}
