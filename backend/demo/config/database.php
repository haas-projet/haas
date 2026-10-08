<?php

return [
    'default' => 'demo',
    'connections' => [
        'demo' => [
            'driver' => 'pgsql',
            'host' => env('DEMO_DB_HOST', '127.0.0.1'),
            'port' => env('DEMO_DB_PORT', '5432'),
            'database' => env('DEMO_DB_DATABASE', 'haas_demo'),
            'username' => env('DEMO_DB_USERNAME', 'haas_demo'),
            'password' => env('DEMO_DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DEMO_DB_SSLMODE', 'prefer'),
        ],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
];
