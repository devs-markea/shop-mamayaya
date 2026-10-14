<?php

namespace Tests\Feature\Services;

use App\Services\Cache\CacheTag;
use App\Services\Cache\ShopCache;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ShopCacheTest extends TestCase
{
    private int $loads = 0;

    private function cache(): ShopCache
    {
        return $this->app->make(ShopCache::class);
    }

    private function read(?callable $load = null, int $fresh = 60, int $stale = 120): mixed
    {
        return $this->cache()->remember(CacheTag::Catalog, 'catalogo', $fresh, $stale, function () use ($load) {
            $this->loads++;

            return $load ? $load() : "lectura {$this->loads}";
        });
    }

    private static function failing(): callable
    {
        return fn () => throw new RuntimeException('La API respondio 503.');
    }

    #[Test]
    public function mientras_esta_fresco_no_vuelve_a_la_api(): void
    {
        $this->assertSame('lectura 1', $this->read());
        $this->assertSame('lectura 1', $this->read());
        $this->assertSame(1, $this->loads);
    }

    #[Test]
    public function caducado_vuelve_a_leer(): void
    {
        $this->read();
        $this->travel(61)->seconds();

        $this->assertSame('lectura 2', $this->read());
    }

    #[Test]
    public function si_la_api_falla_sirve_la_copia_vieja_dentro_de_su_ventana(): void
    {
        $this->read();
        $this->travel(61)->seconds();

        $this->assertSame('lectura 1', $this->read(self::failing()));
    }

    #[Test]
    public function pasada_la_ventana_el_fallo_sigue_su_camino(): void
    {
        $this->read();
        $this->travel(60 + 121)->seconds();

        $this->expectException(RuntimeException::class);
        $this->read(self::failing());
    }

    #[Test]
    public function un_fallo_nunca_se_guarda(): void
    {
        try {
            $this->read(self::failing());
        } catch (RuntimeException) {
        }

        $this->assertSame('lectura 2', $this->read());
    }

    #[Test]
    public function nunca_guarda_menos_del_minimo(): void
    {
        $this->read(fresh: 1);
        $this->travel(29)->seconds();

        $this->assertSame('lectura 1', $this->read(fresh: 1));
    }

    #[Test]
    public function la_purga_obliga_a_volver_a_leer(): void
    {
        $this->read();
        $this->cache()->purge(CacheTag::All);

        $this->assertSame('lectura 2', $this->read());
    }

    #[Test]
    public function purgar_una_etiqueta_no_toca_las_demas(): void
    {
        $this->read();
        $this->cache()->purge(CacheTag::Schedule);

        $this->assertSame('lectura 1', $this->read());
    }
}
