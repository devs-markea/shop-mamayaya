<?php

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Env;

// Keep the .env values out of the process environment. With putenv() they'd be written
// into the PHP worker, which on shared hosting (SiteGround) can also serve the staging
// copy or another site, and getenv() would hand that app this one's DB_* (or the other
// way around). Without it they live only in this request's $_ENV and $_SERVER. Before
// configure(): it has to run before anything reads the environment, artisan included.
Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The store's cookies are not encrypted: the screens' JavaScript writes the
        // checkout draft and the order pointer, and the server reads them.
        $middleware->encryptCookies(except: [
            'sf_cart',
            'sf_checkout',
            'sf_order',
        ]);

        // The /api/* proxy starts the cart session with Cookie::queue, and the `api`
        // group doesn't ship with the middleware that puts those cookies on the response.
        $middleware->api(append: [
            AddQueuedCookiesToResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
