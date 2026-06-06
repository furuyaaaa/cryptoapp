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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'bitflyer' => [
        'base_url' => env('BITFLYER_BASE_URL', 'https://api.bitflyer.com'),
    ],

    'bitbank' => [
        'base_url' => env('BITBANK_BASE_URL', 'https://api.bitbank.cc'),
    ],

    'coincheck' => [
        'base_url' => env('COINCHECK_BASE_URL', 'https://coincheck.com'),
    ],

    'gmo_coin' => [
        'base_url' => env('GMO_COIN_BASE_URL', 'https://api.coin.z.com/private'),
    ],

    'zaif' => [
        'base_url' => env('ZAIF_BASE_URL', 'https://api.zaif.jp'),
    ],

    'binance' => [
        'base_url' => env('BINANCE_BASE_URL', 'https://api.binance.com'),
    ],

];
