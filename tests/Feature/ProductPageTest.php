<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesShopApi;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use FakesShopApi;

    private function fakeProduct(mixed $response): void
    {
        $this->fakeShopApi(more: ['erp.test/api/products/*' => $response]);
    }

    #[Test]
    public function pinta_la_ficha_con_sus_grupos_y_el_total_inicial(): void
    {
        $this->fakeProduct(Http::response(['data' => $this->productDetail()]));

        $response = $this->get('/mamayaya/tacos-al-pastor')->assertOk();

        $response->assertSee('<title>Tacos al pastor — Tienda de Prueba</title>', false)
            ->assertSee('<meta name="description" content="Con piña y cilantro" />', false)
            // The long promo label, next to the name.
            ->assertSee('Descuento de 20%')
            // The default variant (already discounted) plus the preselected sauce: 120 + 5.
            ->assertSee('data-total-template="Agregar — {total}">Agregar — $125.00', false)
            ->assertSee('data-base-price="120"', false)
            // Discounted variant: name, struck-through list price and final price.
            ->assertSee('<span class="option__name">Orden grande</span> — <s class="price-was">$150.00</s> <span class="option__price-final">$120.00</span>', false)
            // Its own photo travels on its radio.
            ->assertSee('data-image-src="https://erp.test/storage/grande.jpg"', false)
            // An unnamed variant still gets a label.
            ->assertSee('<span class="option__name">Variante</span> — $200.00', false)
            // Rules, worded like the panel; an inert group is read-only.
            ->assertSee('Hasta 4 unidades')
            ->assertSee('No se puede elegir ninguna opcion')
            ->assertSee('data-inert', false)
            // The quantity counter's ceiling: maxPerOption (2) bounded by max (4).
            ->assertSee('Agregar una unidad de Queso', false);

        $this->assertMatchesRegularExpression('/id="7-70"[^>]*disabled/s', $response->getContent());
    }

    #[Test]
    public function el_platillo_sin_variantes_manda_su_unica_variante(): void
    {
        $this->fakeProduct(Http::response(['data' => $this->productDetail([
            'hasVariants' => false,
            'defaultVariantId' => null,
            'variants' => [['id' => '99', 'name' => '', 'price' => 80, 'finalPrice' => null, 'savings' => null, 'image' => null]],
            'customizations' => [],
            'promotion' => null,
        ])]));

        $this->get('/mamayaya/tacos-al-pastor')
            ->assertOk()
            ->assertSee('data-variant-id="99"', false)
            ->assertDontSee('Elige una opción');
    }

    #[Test]
    public function sin_foto_monta_la_cabecera_de_vuelta_y_una_sola_columna(): void
    {
        $this->fakeProduct(Http::response(['data' => $this->productDetail(['image' => ['url' => '', 'alt' => '']])]));

        $this->get('/mamayaya/tacos-al-pastor')
            ->assertOk()
            ->assertSee('topbar topbar--compact topbar--mobile', false)
            ->assertDontSee('data-product-hero', false)
            ->assertDontSee('product-page__grid--split', false);
    }

    #[Test]
    public function un_platillo_que_no_existe_es_el_404_de_la_tienda(): void
    {
        $this->fakeProduct(Http::response(['message' => 'Not found'], 404));

        $this->get('/mamayaya/no-existe')
            ->assertNotFound()
            ->assertSee('Platillo no encontrado')
            ->assertSee('Codigo SF-D404')
            ->assertSee('data-retry-mode="none"', false)
            ->assertSee('Volver al menu');
    }

    #[Test]
    public function con_la_api_caida_responde_503_y_se_reintenta_sola(): void
    {
        $this->fakeProduct(Http::response('Bad Gateway', 502));

        $this->get('/mamayaya/tacos-al-pastor')
            ->assertStatus(503)
            ->assertSee('No pudimos cargar el platillo')
            ->assertSee('Codigo SF-D502')
            ->assertSee('data-retry-mode="auto"', false);
    }

    #[Test]
    public function con_un_429_no_se_recarga_sola(): void
    {
        $this->fakeProduct(Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '20']));

        $this->get('/mamayaya/tacos-al-pastor')
            ->assertStatus(503)
            ->assertSee('El menu esta muy solicitado')
            ->assertSee('El menu vuelve en 20 segundos.')
            ->assertSee('data-retry-mode="manual"', false);
    }

    #[Test]
    public function no_disponible_dice_por_que_y_vuelve_al_menu(): void
    {
        $this->fakeProduct(Http::response(['data' => $this->productDetail(['available' => false, 'unavailableReason' => 'no_price'])]));

        $this->get('/mamayaya/tacos-al-pastor')
            ->assertOk()
            ->assertSee('Este platillo todavia no tiene precio, asi que no se puede pedir.')
            ->assertDontSee('data-total-template', false)
            ->assertSee('href="/mamayaya">', false);
    }

    #[Test]
    public function con_la_tienda_cerrada_se_lee_pero_no_se_compra(): void
    {
        $closed = [...$this->openSchedule(), 'isOpen' => false, 'closesAt' => null, 'opensAt' => ['dayOfWeek' => 1, 'time' => '09:00', 'inMinutes' => 600]];

        $this->fakeShopApi(
            schedule: Http::response(['data' => $closed]),
            more: ['erp.test/api/products/*' => Http::response(['data' => $this->productDetail()])],
        );

        $this->get('/mamayaya/tacos-al-pastor')
            ->assertOk()
            ->assertSee('Orden grande')
            ->assertDontSee('product-page__buy', false);
    }

    #[Test]
    public function las_rutas_fijas_le_ganan_al_comodin(): void
    {
        $this->get('/mamayaya/confirmado?order=1')->assertRedirect('/mamayaya/recibido?order=1');
    }
}
