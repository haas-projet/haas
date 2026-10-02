<?php

namespace App\Support\Identity;

use App\Exceptions\Identity\RegistrationUnavailable;

final class RegistrationTerms
{
    public function currentVersion(): string
    {
        $version = config('registration.terms_version');

        if (! is_string($version) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/', $version) !== 1) {
            throw new RegistrationUnavailable('La version des conditions n’est pas configurée.');
        }

        return $version;
    }
}
