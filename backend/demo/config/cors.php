<?php

return [
    'paths' => ['api/v1/b2/*'],
    'allowed_methods' => ['POST', 'OPTIONS'],
    'allowed_origins' => [env('DEMO_FRONTEND_ORIGIN', 'https://demo.example.com')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'Idempotency-Key'],
    'exposed_headers' => ['Retry-After', 'X-Request-ID', 'X-Idempotent-Replay'],
    'max_age' => 600,
    'supports_credentials' => false,
];
