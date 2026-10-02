<?php

$origins = env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173');

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register', 'forgot-password', 'reset-password', 'email/*'],
    'allowed_methods' => ['GET', 'HEAD', 'POST', 'PATCH', 'OPTIONS'],
    'allowed_origins' => is_string($origins) ? array_map('trim', explode(',', $origins)) : [],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN', 'Idempotency-Key'],
    'exposed_headers' => ['Retry-After', 'X-Request-ID'],
    'max_age' => 600,
    'supports_credentials' => true,
];
