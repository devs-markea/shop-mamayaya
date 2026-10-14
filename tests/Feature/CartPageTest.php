<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesCarts;
use Tests\Concerns\FakesShopApi;
use Tests\TestCase;

class CartPageTest extends TestCase
{
    use FakesCarts;
    use FakesShopApi;

    private const TOKEN = '01JAAAAAAAAAAAAAAAAAAAAAAA';

    private function fakeCart(mixed $cart, array $more = [], mixed $schedule = null): void
    {
        $this->fakeShopApi(schedule: $schedule, more: ['erp.test/api/cart' => $cart, ...$more]);
    }

    private function visit(?string $draft = null)
    {
        $request = $this->withUnencryptedCookie('sf_cart', self::TOKEN);

        if ($draft !== null) {
            $request = $request->withUnencryptedCookie('sf_checkout', $draft);
        }

        return $request->get('/mamayaya/carrito');
    }

    #[Test]
    public function pinta_el_pedido_y_cierra_la_cuenta(): void
    {
        $this->fakeCart(Http::response(['data' => $this->cart()]));

        $this->visit()
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow" />', false)
            ->assertSee('<title>Mi pedido — Tienda de Prueba</title>', false)
            ->assertSee('Pedido $575.00')
            // The lines, painted by CartView.
            ->assertSee('Orden grande · Roja · Queso x2')
            ->assertSee('Ver las 3 unidades')
            ->assertSee('data-line-id="d4"', false)
            // 575 crosses the 400 threshold: free shipping, and the bar says so.
            ->assertSee("¡Ya tienes envio gratis! \u{1F389}")
            ->assertSee('data-state="free" data-cart-shipping>Gratis', false)
            ->assertSee('data-cart-discount>− $120.00', false)
            ->assertSee('data-threshold="400"', false)
            ->assertSee('href="/mamayaya/datos"', false);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/cart') && $request->hasHeader('X-Cart-Token', self::TOKEN));
    }

    #[Test]
    public function la_primera_visita_abre_sesion_y_ofrece_volver_al_menu(): void
    {
        $this->fakeCart(Http::response(['data' => $this->cart(['lines' => [], 'items' => [], 'total' => 0, 'subtotal' => 0, 'discountTotal' => 0, 'itemsCount' => 0])]));

        $response = $this->get('/mamayaya/carrito')
            ->assertOk()
            ->assertSee('Tu carrito esta vacio.')
            ->assertSee('Ver el menu')
            ->assertDontSee('shipping-progress', false)
            // The summary card is always there, with its button off.
            ->assertSee('<button class="cart-summary__action btn btn-primary" type="button" disabled>Continuar con la entrega</button>', false);

        $this->assertNotNull($response->getCookie('sf_cart', decrypt: false));
    }

    #[Test]
    public function con_la_api_caida_no_dice_que_el_carrito_esta_vacio(): void
    {
        $this->fakeCart(Http::response('Bad Gateway', 502));

        $this->visit()
            ->assertOk()
            ->assertSee('No pudimos cargar tu pedido.')
            ->assertSee('Codigo SF-C502')
            ->assertSee('data-retry-mode="manual"', false)
            ->assertDontSee('Tu carrito esta vacio.');
    }

    #[Test]
    public function con_la_tienda_cerrada_no_se_puede_continuar(): void
    {
        $closed = [...$this->openSchedule(), 'isOpen' => false, 'closesAt' => null, 'opensAt' => ['dayOfWeek' => 1, 'time' => '09:00', 'inMinutes' => 600]];

        $this->fakeCart(Http::response(['data' => $this->cart()]), schedule: Http::response(['data' => $closed]));

        $this->visit()
            ->assertOk()
            ->assertSee('Los pedidos están deshabilitados.')
            ->assertSee('<button class="action-bar__button btn btn-primary" type="button" disabled>', false);
    }

    #[Test]
    public function recupera_los_comentarios_y_el_modo_del_borrador(): void
    {
        $this->fakeCart(Http::response(['data' => $this->cart()]));

        $draft = rawurlencode(json_encode(['deliveryType' => 'pickup', 'comments' => 'Sin cebolla & sin chile']));

        $this->visit($draft)
            ->assertOk()
            ->assertSee('data-auto-grow>Sin cebolla &amp; sin chile</textarea>', false)
            // Pickup: no shipping row.
            ->assertSee('cart-summary__row d-none" data-cart-shipping-row', false);
    }

    #[Test]
    public function con_ubicacion_compartida_recupera_la_cotizacion_de_la_sesion(): void
    {
        $this->fakeCart(
            Http::response(['data' => $this->cart(['total' => 150, 'subtotal' => 150, 'discountTotal' => 0])]),
            ['erp.test/api/shipping/quote' => Http::response(['data' => ['quoteId' => 'q1', 'serviceable' => true, 'distanceKm' => 4.26, 'cost' => 45, 'free' => false]])],
        );

        $draft = rawurlencode(json_encode(['customer' => ['name' => 'Ana', 'phone' => '9981112233', 'locationUrl' => 'https://www.google.com/maps?q=21.16,-86.82']]));

        $this->visit($draft)
            ->assertOk()
            ->assertSee('data-state="quoted" data-cart-shipping>$45.00', false)
            // 150 + 45: the shipping adds up in the card.
            ->assertSee('data-cart-total>$195.00', false)
            ->assertSee('Estas a $250.00 de obtener envio gratis');
    }
}
