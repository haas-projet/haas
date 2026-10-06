<?php

namespace App\Queries\Identity;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class AccountAccessQuery
{
    /** @return array{message: string, contact_email: ?string} */
    public function get(): array
    {
        $email = config('account.support_email');
        if ($email !== null && $email !== '' && (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new ServiceUnavailableHttpException;
        }

        return [
            'message' => $email
                ? 'Si votre compte est suspendu, vous pouvez demander un réexamen à cette adresse. N’envoyez jamais votre mot de passe ni un lien de connexion.'
                : 'Le contact de recours n’est pas encore configuré. Réessayez ultérieurement.',
            'contact_email' => $email ?: null,
        ];
    }
}
