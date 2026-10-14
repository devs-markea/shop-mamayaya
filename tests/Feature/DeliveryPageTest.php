<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesCarts;
use Tests\Concerns\FakesShopApi;
use Tests\TestCase;

class DeliveryPageTest extends TestCase
{
    use FakesCarts;
    use FakesShopApi;

    private function visit(mixed $cart, ?array $draft = null, array $more = [])
    {
        $this->fakeShopApi(more: ['erp.test/api/cart' => $cart, ...$more]);

        $request = $this->withUnencryptedCookie('sf_cart', '01JAAAAAAAAAAAAAAAAAAAAAAA');

        if ($draft !== null) {
            $request = $request->withUnencryptedCookie('sf_checkout', rawurlencode(json_encode($draft)));
        }

        return $request->get('/mamayaya/datos');
    }

    #[Test]
    public function pide_contacto_y_donde_entregar(): void
    {
        config(['shop.google_maps_key' => 'browser-key']);

        $this->visit(Http::response(['data' => $this->cart()]))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow" />', false)
            ->assertSee('<title>Datos de entrega — Tienda de Prueba</title>', false)
            ->assertSee('id="delivery-home" value="domicilio" checked', false)
            ->assertSee('data-address-block', false)
            // Delivery: the written address is required.
            ->assertSee('id="field-street"', false)
            ->assertSee('data-maps-key="browser-key"', false)
            ->assertSee('data-products="575"', false);

        $content = $this->visit(Http::response(['data' => $this->cart()]))->getContent();
        $this->assertMatchesRegularExpression('/id="field-street"[^>]*required/s', $content);
        $this->assertMatchesRegularExpression('/data-location-map\s+data-maps-key="browser-key"/', $content);
        $this->assertDoesNotMatchRegularExpression('/\shidden\s+data-address-block/', $content);
        $this->assertStringContainsString('Continuar con el pago</button>', $content);
    }

    #[Test]
    public function al_recoger_no_pide_direccion(): void
    {
        $content = $this->visit(Http::response(['data' => $this->cart()]), ['deliveryType' => 'pickup'])
            ->assertOk()
            ->assertSee('id="delivery-pickup" value="recoger" checked', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/\shidden\s+data-address-block/', $content);

        $this->assertDoesNotMatchRegularExpression('/id="field-street"[^>]*required/s', $content);
        // The name and the phone are asked anyway: the phone is the ERP's key.
        $this->assertMatchesRegularExpression('/id="field-phone"[^>]*required/s', $content);
    }

    #[Test]
    public function con_el_carrito_vacio_vuelve_al_pedido(): void
    {
        $this->visit(Http::response(['data' => $this->cart(['lines' => [], 'items' => []])]))
            ->assertRedirect('/mamayaya/carrito');
    }

    #[Test]
    public function con_la_api_caida_sirve_el_formulario_igual(): void
    {
        $this->visit(Http::response('Bad Gateway', 502))
            ->assertOk()
            ->assertSee('data-delivery-form', false)
            ->assertSee('data-products="0"', false);
    }

    #[Test]
    public function avisa_cuando_la_ubicacion_cae_fuera_de_la_zona(): void
    {
        config(['shop.test_mode' => false]);

        $draft = [
            'customer' => ['name' => 'Ana', 'phone' => '9981112233', 'locationUrl' => 'https://www.google.com/maps?q=20.63,-87.07'],
            'shipping' => ['cost' => null, 'km' => 72.4, 'quoteId' => 'q9', 'serviceable' => false, 'notice' => 'Tu ubicacion esta en Playa del Carmen.'],
        ];

        $response = $this->visit(Http::response(['data' => $this->cart()]), $draft)
            ->assertOk()
            ->assertSee('Tu ubicacion esta en Playa del Carmen.')
            // Out of area there's nothing to promise, not even the free shipping.
            ->assertSee('data-state="pending" data-cart-shipping>Por cotizar', false);

        // Revealed: the note has no `hidden` attribute.
        $this->assertMatchesRegularExpression('/<div class="info-note"\s+data-area-notice\s*>/', $response->getContent());
    }

    #[Test]
    public function el_modo_de_pruebas_silencia_el_aviso(): void
    {
        config(['shop.test_mode' => true]);

        $draft = [
            'customer' => ['locationUrl' => 'https://www.google.com/maps?q=20.63,-87.07'],
            'shipping' => ['cost' => null, 'serviceable' => false, 'notice' => 'Tu ubicacion esta en Playa del Carmen.'],
        ];

        $this->visit(Http::response(['data' => $this->cart()]), $draft)
            ->assertOk()
            ->assertDontSee('Tu ubicacion esta en Playa del Carmen.')
            ->assertSee('data-test-mode', false);
    }
}
