<?php

use App\Http\Middleware\RequireCsrfToken;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

$domains = env('SANCTUM_STATEFUL_DOMAINS', 'localhost:5173,localhost:8000');

return [
    'stateful' => is_string($domains) ? array_map('trim', explode(',', $domains)) : [],
    'guard' => ['web'],
    'expiration' => null,
    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => RequireCsrfToken::class,
    ],
];
