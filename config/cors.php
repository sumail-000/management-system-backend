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
        env('FRONTEND_URL', 'http://localhost:8080'),
        env('APP_URL', 'http://localhost:8000'),
        'http://localhost:8080',
        'http://localhost:8081',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:8080',
    ]),

    'allowed_origins_patterns' => [
        // No patterns needed for production
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-New-Token',
        'X-Token-Refreshed',
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];