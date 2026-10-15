<?php

namespace Tests\Feature\Api;

use App\Services\Cache\CacheTag;
use App\Services\Cache\ShopCache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CacheInvalidateTest extends TestCase
{
    private function purge(array $body, ?string $key = 'test-purge-key')
    {
        return $this->postJson('/api/cache/invalidate', $body, $key === null ? [] : ['X-Cache-Purge-Key' => $key]);
    }

    #[Test]
    public function sin_clave_configurada_no_purga_nada(): void
    {
        config(['shop.cache_purge_key' => null]);

        $this->purge(['tags' => ['tienda']])->assertStatus(503);
    }

    #[Test]
    public function rechaza_una_clave_incorrecta_o_ausente(): void
    {
        $this->purge(['tags' => ['tienda']], 'otra')->assertStatus(401);
        $this->purge(['tags' => ['tienda']], null)->assertStatus(401);
    }

    #[Test]
    public function rechaza_etiquetas_desconocidas_o_vacias(): void
    {
        $this->purge(['tags' => ['tienda', 'otra-cosa']])->assertStatus(422);
        $this->purge(['tags' => []])->assertStatus(422);
        $this->purge(['tags' => 'tienda'])->assertStatus(422);
        $this->purge([])->assertStatus(422);
    }

    #[Test]
    public function purga_y_la_siguiente_lectura_vuelve_a_la_api(): void
    {
        $cache = $this->app->make(ShopCache::class);
        $loads = 0;
        $read = function () use ($cache, &$loads) {
            return $cache->remember(CacheTag::Config, 3600, 0, function () use (&$loads) {
                return ++$loads;
            });
        };

        $read();
        $this->purge(['tags' => ['tienda-config']])->assertNoContent();
        $read();

        $this->assertSame(2, $loads);
    }
}
