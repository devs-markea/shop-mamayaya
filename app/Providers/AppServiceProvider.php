<?php

namespace App\Providers;

use App\Services\Cache\ShopCache;
use App\Services\Cart\CartSession;
use App\Services\Catalog\CatalogService;
use App\Services\ShopApi\ShopApiClient;
use App\Services\Store\StoreService;
use App\Services\Theme\ThemeRepository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ShopApiClient::class, fn (Application $app) => new ShopApiClient(
            $app->make(HttpFactory::class),
            $app['config']->get('services.shop_api'),
        ));

        $this->app->singleton(ShopCache::class, fn (Application $app) => new ShopCache(
            Cache::store($app['config']->get('shop.cache.store')),
            $app['config']->get('shop.cache.version'),
            $app['config']->get('shop.cache.min_fresh'),
        ));

        $this->app->singleton(CartSession::class, fn (Application $app) => new CartSession(
            $app->make('cookie'),
            $app['config']->get('shop.cookies.cart'),
            $app['config']->get('shop.cart_lifetime'),
            secure: $app->isProduction(),
        ));

        $this->app->singleton(ThemeRepository::class, fn () => new ThemeRepository(resource_path('themes')));

        $this->app->singleton(StoreService::class, fn (Application $app) => new StoreService(
            $app->make(ShopApiClient::class),
            $app->make(ShopCache::class),
            $app['config']->get('store-fallback', []),
            (bool) $app['config']->get('shop.schedule_from_shifts'),
            $app['config']->get('shop.cache'),
        ));

        $this->app->singleton(CatalogService::class, fn (Application $app) => new CatalogService(
            $app->make(ShopApiClient::class),
            $app->make(ShopCache::class),
            $app['config']->get('shop.cache.catalog'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // On SiteGround HTTPS is terminated by the nginx in front of Apache, and PHP
        // may see the request as plain http: without this, the URLs Laravel generates
        // (Vite assets, redirects) would come out as http:// on an https page.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
