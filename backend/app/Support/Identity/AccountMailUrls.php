<?php

namespace App\Support\Identity;

use Carbon\CarbonImmutable;
use Illuminate\Routing\UrlGenerator;
use SensitiveParameter;

final class AccountMailUrls
{
    public function verification(string $id, string $emailHash, int $expiresAt): string
    {
        app(AccountMailConfiguration::class)->validate();
        $urls = clone app(UrlGenerator::class);
        $origin = (string) config('app.url');
        $urls->useOrigin($origin);
        $urls->forceScheme(str_starts_with($origin, 'https://') ? 'https' : 'http');

        return $urls->temporarySignedRoute('verification.verify', CarbonImmutable::createFromTimestampUTC($expiresAt), ['id' => $id, 'hash' => $emailHash]);
    }

    public function reset(string $email, #[SensitiveParameter] string $token): string
    {
        app(AccountMailConfiguration::class)->validate();

        // Le fragment n'est pas envoyé au serveur frontend ni dans le Referer HTTP.
        return config('spa.frontend_url').'/reset-password#'.http_build_query(['token' => $token, 'email' => $email], '', '&', PHP_QUERY_RFC3986);
    }
}
