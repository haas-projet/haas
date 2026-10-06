<?php

namespace App\Notifications\Identity;

use App\Models\User;
use App\Support\Identity\AccountMailUrls;
use Illuminate\Notifications\Messages\MailMessage;

final class VerifyAccountEmail extends AccountMailNotification
{
    public function __construct(string $email)
    {
        parent::__construct($email, 60);
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $this->recipientIsCurrent($notifiable) && ! $notifiable->hasVerifiedEmail();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Vérifiez votre courriel HAAS')->greeting('Bonjour,')
            ->line('Confirmez cette adresse courriel pour participer sur HAAS.')
            ->action('Vérifier mon courriel', app(AccountMailUrls::class)->verification($notifiable->id, $this->emailHash, $this->expiresAt))
            ->line('Ce lien expire une heure après la demande. Connectez-vous au compte concerné pour l’utiliser.')
            ->line('Si vous n’avez pas créé ce compte, ignorez ce message.')->salutation('L’équipe HAAS');
    }
}
