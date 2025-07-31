<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:8081'),
        env('APP_URL', 'http://localhost:8000'),
        // Additional development URLs
        'http://localhost:3000',
        'http://localhost:4173',
        'http://localhost:8080',
        'http://localhost:8082',
        'http://localhost:8083',
        'http://localhost:8084',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:4173',
        'http://127.0.0.1:8080',
        'http://127.0.0.1:8081',
        'http://127.0.0.1:8082',
        'http://127.0.0.1:8083',
        'http://127.0.0.1:8084',
        'https://intake-faced-willing-bear.trycloudflare.com',
    ]),

    'allowed_origins_patterns' => [
        // Support for ngrok tunnels
        '/^https:\/\/[a-z0-9-]+\.ngrok-free\.app$/',
        '/^https:\/\/[a-z0-9-]+\.ngrok\.io$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-New-Token',
        'X-Token-Refreshed',
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];