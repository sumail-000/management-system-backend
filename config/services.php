<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'edamam' => [
        'nutrition' => [
            'app_id' => env('EDAMAM_NUTRITION_APP_ID'),
            'app_key' => env('EDAMAM_NUTRITION_APP_KEY'),
            'api_url' => env('EDAMAM_NUTRITION_API_URL'),
        ],
        'food' => [
            'app_id' => env('EDAMAM_FOOD_APP_ID'),
            'app_key' => env('EDAMAM_FOOD_APP_KEY'),
            'api_url' => env('EDAMAM_FOOD_API_URL'),
        ],
        'recipe' => [
            'app_id' => env('EDAMAM_RECIPE_APP_ID'),
            'app_key' => env('EDAMAM_RECIPE_APP_KEY'),
            'api_url' => env('EDAMAM_RECIPE_API_URL'),
        ],
        'user_id' => env('EDAMAM_USER_ID'),
        'timeout' => env('EDAMAM_TIMEOUT', 30),
        'max_retries' => env('EDAMAM_MAX_RETRIES', 3),
        'retry_delay' => env('EDAMAM_RETRY_DELAY', 1000),
    ],

];
