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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Store API (the ERP)
    |--------------------------------------------------------------------------
    |
    | Only the server talks to it: the browser calls this store's own /api/*
    | and App\Services\ShopApi\ShopApiClient forwards. See .env.example for
    | each variable.
    |
    */

    'shop_api' => [
        // API origin, no trailing slash. No default on purpose: the backend's
        // APP_URL is `http://localhost`, and an equivalent fallback here would
        // slip into production without anyone noticing.
        'url' => env('API_URL'),

        // X-Shop-Key header. Optional: as long as the backend doesn't require it
        // either, the store works without it.
        'key' => env('SHOP_API_KEY'),

        // LARAVEL DOESN'T ASK FOR THIS, THE HOSTING DOES. SiteGround's anti-bot
        // layer answers a `202` and a captcha to calls that don't identify as a
        // browser, and a server can't solve it (it happened on 2026-09-17 and the
        // store lost its catalog). This is Chrome's stable version at the time of
        // writing: check it now and then at https://chromiumdash.appspot.com/schedule
        // and change ONLY the number.
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36',

        // Reads (GET and HEAD): a timeout per attempt, a retry on a 5xx or a
        // network failure, and a ceiling for all of them. The backend lives on
        // shared hosting and the first burst after an idle spell is the one that
        // fails: whoever retries arrives with the processes already up.
        'reads' => [
            'attempt_timeout' => 6.0,   // seconds per attempt
            'total_budget' => 8.0,      // seconds for all of them, waits included
            'max_attempts' => 3,
            'min_attempt' => 1.0,       // the least that must remain to open another
            'backoff_ms' => [250, 750], // wait before each retry
        ],

        // Whatever changes state (POST, PATCH, DELETE): a single request with a
        // long timeout. Cutting a checkout after a few seconds while the backend
        // is still creating the order is how you end up with two orders.
        'send_timeout' => 120,
    ],

];
