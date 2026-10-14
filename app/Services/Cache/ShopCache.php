<?php

namespace App\Services\Cache;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cache de las lecturas que arman cualquier pantalla: catalogo, horario y configuracion.
 *
 * POR QUE EXISTE. Todas las pantallas las piden, y el backend vive en un hosting
 * compartido donde cada peticion arranca un proceso de PHP. Guardarlas quita casi todas
 * esas lecturas y aplasta las rafagas: cien compradores entrando a la vez no piden el
 * catalogo cien veces.
 *
 * Sustituye a la Runtime Cache de Vercel del proyecto anterior y funciona con cualquier
 * driver de Laravel (en SiteGround, `file`). Lo que se guarda tiene dos plazos:
 *
 *   fresh  se sirve sin preguntar a la API
 *   stale  despues de caducar se sigue sirviendo, pero SOLO si la API falla
 *
 * LO QUE NUNCA SE GUARDA: un fallo. Ni un 5xx, ni un 429, ni un plazo agotado, ni una
 * respuesta sin JSON. Lo garantiza ShopApiClient::unwrap(), que convierte esas respuestas
 * en excepcion: aqui solo se guarda lo que `$load` devuelve sin lanzar.
 *
 * UNA AVERIA DE LA CACHE NO TUMBA LA TIENDA. Si no se puede leer, escribir o bloquear,
 * se pide a la API como si no hubiera copia.
 */
class ShopCache
{
    /** Lo que vive el candado de una lectura: por encima del presupuesto de la API. */
    private const LOCK_SECONDS = 15;

    /** Lo que se espera a otra peticion que ya esta leyendo lo mismo. */
    private const WAIT_SECONDS = 10;

    public function __construct(
        private readonly Repository $store,
        private readonly string $version,
        private readonly int $minFresh,
    ) {}

    /**
     * Lo guardado si sigue fresco; si no, lo que devuelva `$load`.
     *
     * Las peticiones que llegan a la vez con la copia caducada no salen todas a la API: la
     * primera toma el candado y las demas esperan su resultado.
     *
     * @template T
     *
     * @param  string  $name  Nombre corto, para la clave y para el log: `catalogo`, `horario`…
     * @param  int  $fresh  Segundos que se sirve sin preguntar.
     * @param  int  $stale  Segundos que se sigue sirviendo despues, si la API falla.
     * @param  callable(): T  $load  La lectura de verdad. Si lanza, no se guarda nada.
     * @return T
     */
    public function remember(CacheTag $tag, string $name, int $fresh, int $stale, callable $load): mixed
    {
        $key = $this->key($tag, $name);
        $entry = $this->read($key);

        if ($this->isFresh($entry)) {
            return $entry['value'];
        }

        $lock = $this->acquire($key);

        try {
            // Mientras se esperaba el candado, otra peticion pudo haberla refrescado.
            if ($lock) {
                $latest = $this->read($key);

                if ($this->isFresh($latest)) {
                    return $latest['value'];
                }

                $entry = $latest ?? $entry;
            }

            return $this->refresh($key, $name, $fresh, $stale, $load, $entry);
        } finally {
            $lock?->release();
        }
    }

    /**
     * Vacia lo guardado con estas etiquetas: la siguiente visita lo vuelve a pedir.
     *
     * No borra claves una a una: sube la generacion de la etiqueta, que forma parte de la
     * clave, y lo de antes muere por su TTL. Asi funciona igual con cualquier driver,
     * tambien con los que no tienen etiquetas (`file`, `database`). Lanza si la cache no
     * responde, para que quien pidio la purga se entere.
     */
    public function purge(CacheTag ...$tags): void
    {
        $targets = [];

        foreach ($tags as $tag) {
            foreach ($tag->expand() as $concrete) {
                $targets[$concrete->value] = $concrete;
            }
        }

        foreach ($targets as $tag) {
            $this->store->forever($this->generationKey($tag), $this->generation($tag) + 1);
        }

        Log::info('[cache] purgado: '.implode(', ', array_keys($targets)));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $load
     * @param  array{value: T, fetched_at: int, fresh_until: int, stale_until: int}|null  $entry
     * @return T
     */
    private function refresh(string $key, string $name, int $fresh, int $stale, callable $load, ?array $entry): mixed
    {
        try {
            $value = $load();
        } catch (Throwable $e) {
            // La copia vieja es mejor que el aviso de averia, pero solo un rato y solo si
            // la hay: pasado `stale_until` el fallo sigue su camino y la pantalla lo cuenta.
            if ($entry && now()->getTimestamp() < $entry['stale_until']) {
                Log::warning("[cache] {$name}: la API fallo, se sirve la copia del ".date(DATE_ATOM, $entry['fetched_at']), [
                    'error' => $e->getMessage(),
                ]);

                return $entry['value'];
            }

            throw $e;
        }

        $now = now()->getTimestamp();
        $freshUntil = $now + max($fresh, $this->minFresh);
        $staleUntil = $freshUntil + max($stale, 0);

        $this->write($key, [
            'value' => $value,
            'fetched_at' => $now,
            'fresh_until' => $freshUntil,
            'stale_until' => $staleUntil,
        ], $staleUntil - $now);

        Log::info("[cache] {$name}: leido de la API, vale hasta ".date(DATE_ATOM, $freshUntil));

        return $value;
    }

    /**
     * El candado de la lectura, esperando a quien lo tenga. `null` si no se pudo tomar:
     * entonces se lee sin el, que es peor para la API pero no deja a nadie sin pagina.
     */
    private function acquire(string $key): ?Lock
    {
        $store = $this->store->getStore();

        if (! $store instanceof LockProvider) {
            return null;
        }

        try {
            $lock = $store->lock("{$key}:lock", self::LOCK_SECONDS);

            return $lock->block(self::WAIT_SECONDS) ? $lock : null;
        } catch (LockTimeoutException) {
            return null;
        } catch (Throwable $e) {
            Log::error('[cache] no se pudo tomar el candado', ['key' => $key, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * La entrada guardada, o null. NUNCA LANZA: si la cache no responde o devuelve algo
     * con otra forma, es como no tener copia.
     */
    private function read(string $key): ?array
    {
        try {
            $raw = $this->store->get($key);
        } catch (Throwable $e) {
            Log::error('[cache] no se pudo leer', ['key' => $key, 'error' => $e->getMessage()]);

            return null;
        }

        if (! is_array($raw)
            || ! array_key_exists('value', $raw)
            || ! is_int($raw['fresh_until'] ?? null)
            || ! is_int($raw['stale_until'] ?? null)
            || ! is_int($raw['fetched_at'] ?? null)) {
            return null;
        }

        return $raw;
    }

    /** Tampoco lanza: si no se puede guardar, se sirve igual lo leido. */
    private function write(string $key, array $entry, int $ttl): void
    {
        try {
            $this->store->put($key, $entry, $ttl);
        } catch (Throwable $e) {
            Log::error('[cache] no se pudo guardar', ['key' => $key, 'error' => $e->getMessage()]);
        }
    }

    private function isFresh(?array $entry): bool
    {
        return $entry !== null && now()->getTimestamp() < $entry['fresh_until'];
    }

    private function key(CacheTag $tag, string $name): string
    {
        return "shop:{$this->version}:{$tag->value}:g{$this->generation($tag)}:{$name}";
    }

    private function generation(CacheTag $tag): int
    {
        try {
            return (int) $this->store->get($this->generationKey($tag), 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function generationKey(CacheTag $tag): string
    {
        return "shop:generation:{$tag->value}";
    }
}
