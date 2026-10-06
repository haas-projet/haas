<?php

namespace App\Support\Identity;

use LogicException;

final class AccountMailConfiguration
{
    public function validate(): void
    {
        app(SpaConfiguration::class)->validate();
        $mailer = config('mail.default');
        if (! is_string($mailer) || ! in_array($mailer, app()->isProduction() ? ['smtp'] : ['smtp', 'array'], true)
            || config('mail.mailers.'.$mailer.'.transport') !== $mailer) {
            throw new LogicException('Transport des courriels de compte invalide : SMTP réel ou array local, jamais log.');
        }
        if (config('queue.connections.account-mail.driver') !== 'database'
            || config('queue.connections.account-mail.connection') !== null
            || config('queue.connections.account-mail.after_commit') !== false) {
            throw new LogicException('Les courriels de compte exigent la file SQL de la transaction courante.');
        }
    }
}
