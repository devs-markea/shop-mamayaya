<?php

namespace App\Console\Commands;

use App\Services\ShopApi\ShopApiClient;
use App\Services\Theme\ThemeRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Comprueba que la tienda esta lista para servir.
 *
 * En Astro esto lo hacia el build: sin API_URL o con un STORE_THEME sin su JSON, no se
 * desplegaba. En Laravel no hay build del servidor, asi que se corre despues de cada
 * despliegue —`php artisan shop:check --api`— y falla nombrando lo que falta.
 */
class ShopCheck extends Command
{
    protected $signature = 'shop:check {--api : Pide tambien GET /api/store para comprobar la conexion con el ERP}';

    protected $description = 'Comprueba la configuracion de la tienda (variables, tema, cache, assets y, con --api, la API)';

    private bool $failed = false;

    public function handle(ThemeRepository $themes, ShopApiClient $api): int
    {
        $this->checkApiUrl();
        $this->checkTheme($themes);
        $this->checkSecrets();
        $this->checkEnvironment();
        $this->checkCache();
        $this->checkAssets();

        if ($this->option('api')) {
            $this->checkApi($api);
        }

        $this->newLine();

        if ($this->failed) {
            $this->components->error('La tienda NO esta lista.');

            return self::FAILURE;
        }

        $this->components->info('La tienda esta lista.');

        return self::SUCCESS;
    }

    private function checkApiUrl(): void
    {
        $url = (string) config('services.shop_api.url');

        if ($url === '') {
            $this->reportFailure('API_URL', 'falta: es el origen de la API de la tienda');
        } elseif (! str_starts_with($url, 'https://') && app()->isProduction()) {
            $this->reportWarning('API_URL', "{$url} no es https");
        } else {
            $this->reportOk('API_URL', $url);
        }
    }

    private function checkTheme(ThemeRepository $themes): void
    {
        try {
            $available = array_keys($themes->all());
        } catch (Throwable $e) {
            $this->reportFailure('Temas', $e->getMessage());

            return;
        }

        $theme = (string) config('shop.theme');

        if (! in_array($theme, $available, true)) {
            $this->reportFailure('STORE_THEME', "\"{$theme}\" no existe. Hay: ".implode(', ', $available));
        } else {
            $this->reportOk('STORE_THEME', $theme);
        }
    }

    private function checkSecrets(): void
    {
        config('services.shop_api.key')
            ? $this->reportOk('SHOP_API_KEY', 'definida')
            : $this->reportWarning('SHOP_API_KEY', 'sin definir: el backend no podra distinguir a esta tienda');

        config('shop.cache_purge_key')
            ? $this->reportOk('SHOP_CACHE_PURGE_KEY', 'definida')
            : $this->reportWarning('SHOP_CACHE_PURGE_KEY', 'sin definir: el boton "Refrescar tienda" del panel no purgara');

        config('shop.google_maps_key')
            ? $this->reportOk('GOOGLE_MAPS_API_KEY', 'definida')
            : $this->reportWarning('GOOGLE_MAPS_API_KEY', 'sin definir: /mamayaya/datos caera al respaldo sin mapa');
    }

    private function checkEnvironment(): void
    {
        if (! app()->isProduction()) {
            $this->reportOk('APP_ENV', app()->environment());

            return;
        }

        config('app.debug')
            ? $this->reportFailure('APP_DEBUG', 'encendido en produccion: ensena trazas y variables a cualquiera')
            : $this->reportOk('APP_DEBUG', 'apagado');

        config('shop.test_mode')
            ? $this->reportWarning('TEST_MODE', 'encendido en produccion: oculta el aviso de la zona de reparto')
            : $this->reportOk('TEST_MODE', 'apagado');
    }

    private function checkCache(): void
    {
        $store = config('shop.cache.store') ?? config('cache.default');
        $key = 'shop:check:'.bin2hex(random_bytes(4));

        try {
            $cache = Cache::store(config('shop.cache.store'));
            $cache->put($key, 'ok', 60);
            $read = $cache->get($key);
            $cache->forget($key);
        } catch (Throwable $e) {
            $this->reportFailure('Cache', "{$store}: {$e->getMessage()}");

            return;
        }

        $read === 'ok'
            ? $this->reportOk('Cache', "{$store}: lee y escribe")
            : $this->reportFailure('Cache', "{$store}: escribe pero no devuelve lo escrito");
    }

    private function checkAssets(): void
    {
        if (is_file(public_path('hot'))) {
            $this->reportOk('Assets', 'servidor de Vite en marcha (public/hot)');
        } elseif (is_file(public_path('build/manifest.json'))) {
            $this->reportOk('Assets', 'public/build/manifest.json');
        } else {
            $this->reportFailure('Assets', 'falta public/build: hay que correr `npm run build` y subir la carpeta');
        }
    }

    private function checkApi(ShopApiClient $api): void
    {
        try {
            $store = $api->get('/api/store');
        } catch (Throwable $e) {
            $this->reportFailure('GET /api/store', $e->getMessage());

            return;
        }

        $this->reportOk('GET /api/store', 'responde: '.($store['name'] ?? 'sin nombre'));
    }

    private function reportOk(string $what, string $detail): void
    {
        $this->components->twoColumnDetail($what, "<fg=green>{$detail}</>");
    }

    private function reportWarning(string $what, string $detail): void
    {
        $this->components->twoColumnDetail($what, "<fg=yellow>{$detail}</>");
    }

    private function reportFailure(string $what, string $detail): void
    {
        $this->failed = true;
        $this->components->twoColumnDetail($what, "<fg=red>{$detail}</>");
    }
}
