<?php

/*
|--------------------------------------------------------------------------
| The store
|--------------------------------------------------------------------------
|
| What decides how this store behaves. The ERP API credentials live in
| config/services.php (`shop_api`). Every environment variable is explained
| in .env.example.
|
*/

return [

    // The color theme: the name of a JSON file in resources/themes/. Unset, it's
    // the gold the store was designed with. `php artisan shop:check` reports a
    // name that doesn't exist; during a visit, an unknown one falls back to the default.
    'theme' => env('STORE_THEME', 'gold'),

    // Silences the delivery-area notice. For the team only: off in production.
    'test_mode' => (bool) env('TEST_MODE', false),

    // Where "open" or "closed" comes from: the backend's `isOpen` (false) or the
    // `days[].shifts` ranges, computed here (true).
    'schedule_from_shifts' => (bool) env('SCHEDULE_FROM_SHIFTS', false),

    // The store's time zone: the schedule's and "open now"'s.
    'timezone' => 'America/Cancun',

    // Google Maps BROWSER key (the /mamayaya/datos location picker). It travels to
    // the browser: it's protected by restricting it by HTTP referrer.
    'google_maps_key' => env('GOOGLE_MAPS_API_KEY'),

    // Key the BACKEND uses to ask for a cache purge (X-Cache-Purge-Key on
    // POST /api/cache/invalidate). Without it the endpoint answers 503.
    'cache_purge_key' => env('SHOP_CACHE_PURGE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Read cache
    |--------------------------------------------------------------------------
    |
    | Catalog, schedule and settings go through App\Services\Cache\ShopCache.
    | `fresh`: how long it's served without asking. `stale`: how long it keeps
    | being served afterwards, but ONLY if the API fails. Seconds.
    |
    */

    'cache' => [
        // null = the application's CACHE_STORE.
        'store' => env('SHOP_CACHE_STORE'),

        // Version of the SHAPE of what's stored. Bumping it starts fresh keys when
        // the API contract changes and lets the old ones expire.
        'version' => 'v1',

        // Below this, every visit would ask again.
        'min_fresh' => 30,

        'catalog' => ['fresh' => 24 * 3600, 'stale' => 3600],
        'config' => ['fresh' => 24 * 3600, 'stale' => 24 * 3600],

        // The backend's `isOpen` expires within a minute; the ranges don't expire.
        'schedule' => ['fresh' => 30, 'stale' => 0],
        'schedule_from_shifts' => ['fresh' => 7 * 24 * 3600, 'stale' => 7 * 24 * 3600],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookies
    |--------------------------------------------------------------------------
    |
    | Not encrypted (bootstrap/app.php): the checkout draft and the order pointer
    | are written by the browser, and the cart token is a ULID that says nothing
    | on its own. The names are repeated there because that file runs before the
    | configuration exists: if you rename one, change it in both places.
    |
    */

    'cookies' => [
        'cart' => 'sf_cart',
        'checkout' => 'sf_checkout',
        'order' => 'sf_order',
    ],

    // How long the cart session lasts, in minutes.
    'cart_lifetime' => 60 * 24 * 30,

];
