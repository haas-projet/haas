<?php

use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Notifications\NotificationServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\ServiceProvider;

return [
    'name' => 'HAAS B2 fictif',
    'env' => env('DEMO_ENV', 'production'),
    'debug' => false,
    'url' => env('DEMO_API_URL', 'https://demo-api.example.com'),
    'timezone' => 'UTC',
    'locale' => 'fr',
    'fallback_locale' => 'fr',
    'key' => null,
    'previous_keys' => [],
    'maintenance' => ['driver' => 'file'],
    'providers' => ServiceProvider::defaultProviders()->except([
        AuthServiceProvider::class,
        PasswordResetServiceProvider::class,
        CookieServiceProvider::class,
        EncryptionServiceProvider::class,
        SessionServiceProvider::class,
        MailServiceProvider::class,
        NotificationServiceProvider::class,
        QueueServiceProvider::class,
    ])->toArray(),
];
