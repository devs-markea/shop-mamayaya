<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesCarts;
use Tests\Concerns\FakesOrders;
use Tests\Concerns\FakesShopApi;
use Tests\TestCase;

class ConfirmationPageTest extends TestCase
{
    use FakesCarts;
    use FakesOrders;
    use FakesShopApi;

    private const TEMPLATES = [
        'delivery' => [
            'bank_transfer' => "Pedido #{folio}\nTotal: {TOTAL}\nCuenta: {notas}",
            'efectivo' => 'Efectivo #{folio}',
            'mercado_pago' => 'Pague el #{folio} con {metodo}',
        ],
        'pickup' => ['efectivo' => 'Recojo el #{folio} en {sucursal}'],
        'orderStatus' => 'Como va el #{folio}? {estado}',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.test_mode' => false]);
    }

    private function visit(?array $pointer = null, mixed $order = null, string $path = '/mamayaya/recibido', array $store = [], ?array $draft = null)
    {
        $this->fakeShopApi(
            store: Http::response(['data' => $this->store([
                'whatsapp' => ['phone' => '529981112233', 'templates' => self::TEMPLATES],
                'bankTransfer' => ['holder' => 'Tienda SA', 'bank' => 'BBVA', 'clabe' => '012180001234567895'],
                ...$store,
            ])]),
            more: [
                'erp.test/api/orders/*' => $order ?? Http::response(['data' => $this->storeOrder()]),
                'erp.test/api/cart' => Http::response(['data' => $this->cart(['items' => [$this->cartItem()]])]),
            ],
        );

        $request = $this->withUnencryptedCookie('sf_cart', '01JAAAAAAAAAAAAAAAAAAAAAAA');

        if ($pointer !== null) {
            $request = $request->withUnencryptedCookie('sf_order', $this->orderPointer($pointer));
        }

        if ($draft !== null) {
            $request = $request->withUnencryptedCookie('sf_checkout', rawurlencode(json_encode($draft)));
        }

        return $request->get($path);
    }

    #[Test]
    public function sin_pedido_al_que_apuntar_vuelve_al_menu_sin_llamar_a_la_api(): void
    {
        Http::fake();

        $this->get('/mamayaya/recibido')->assertRedirect('/mamayaya');

        Http::assertNothingSent();
    }

    #[Test]
    public function un_pedido_que_no_es_de_la_sesion_vuelve_al_menu(): void
    {
        $this->visit(['id' => 'ajeno', 'method' => 'bank_transfer'], Http::response(['message' => 'Not found'], 404))
            ->assertRedirect('/mamayaya');
    }

    #[Test]
    public function la_transferencia_pide_el_comprobante_y_ensena_la_clabe(): void
    {
        $response = $this->visit(['id' => 'ord-1', 'method' => 'bank_transfer', 'deliveryType' => 'delivery'])
            ->assertOk()
            ->assertSee('<title>Pedido recibido — Tienda de Prueba</title>', false)
            ->assertSee('<meta name="description" content="Envia tu comprobante de transferencia para confirmar el pedido." />', false)
            ->assertSee('<span class="steps__number" aria-hidden="true">2</span>', false)
            ->assertSee('Envia el comprobante por WhatsApp')
            ->assertSee('<span class="clabe-card__label">CLABE BBVA</span>', false)
            ->assertSee('<span class="clabe-card__value">0121 8000 1234 567895</span>', false)
            // The flow's template, filled with the order: rule 2 drops the empty notes line.
            ->assertSee('href="https://wa.me/529981112233?text=Pedido%20%2391%0ATotal%3A%20%24630"', false)
            // The second button, with the order status message.
            ->assertSee('href="https://wa.me/529981112233?text=Como%20va%20el%20%2391%3F%20Por%20confirmar"', false)
            ->assertSee('Tu pedido quedara pendiente hasta confirmar el pago')
            ->assertDontSee('security.js');

        // Nothing left pending in the store: the pointer is marked as seen.
        $cookie = $response->getCookie('sf_order', decrypt: false);
        $this->assertSame(['id' => 'ord-1', 'method' => 'bank_transfer', 'deliveryType' => 'delivery', 'seen' => true], json_decode($cookie->getValue(), true));
        $this->assertSame('/', $cookie->getPath());
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    #[Test]
    public function el_efectivo_al_recoger_pide_el_monto_exacto(): void
    {
        $order = $this->storeOrder(['deliveryType' => 'pickup', 'paymentMethod' => 'efectivo', 'shippingTotal' => 0, 'total' => 585, 'shipping' => null]);

        $this->visit(['id' => 'ord-1', 'method' => 'efectivo', 'seen' => true], Http::response(['data' => $order]))
            ->assertOk()
            ->assertSee('Te avisaremos por WhatsApp cuando tu pedido este listo para recoger.')
            ->assertSee('Prepara el monto exacto: $585.00')
            ->assertSee('Pagaras en efectivo al recoger tu pedido')
            ->assertSee('?text=Recojo%20el%20%2391%20en%20Av.%20Siempre%20Viva%20742', false)
            ->assertDontSee('clabe-card', false)
            // Already seen: no cookie is written again.
            ->assertCookieMissing('sf_order');
    }

    #[Test]
    public function mercado_pago_pagado_resume_el_pedido(): void
    {
        $order = $this->storeOrder(['status' => 'in_preparation', 'statusLabel' => 'En preparacion', 'paymentMethod' => 'mercado_pago', 'payment' => ['provider' => 'mercado_pago', 'status' => 'approved', 'redirectUrl' => null]]);

        $this->visit(['id' => 'ord-1', 'method' => 'mercado_pago'], Http::response(['data' => $order]))
            ->assertOk()
            ->assertSee('<h1 class="order-status__title">Pedido confirmado</h1>', false)
            ->assertSee('<p class="order-status__folio">#91</p>', false)
            ->assertSee('<span class="order-receipt__quantity">×2</span>', false)
            ->assertSee('<span class="order-receipt__price">$250.00</span>', false)
            ->assertSee('<span>Mercado Pago</span>', false)
            ->assertSee('<span class="order-receipt__total">$630.00</span>', false)
            ->assertSee('?text=Pague%20el%20%2391%20con%20Mercado%20Pago', false)
            ->assertCookie('sf_order', encrypted: false);
    }

    #[Test]
    public function un_cobro_rechazado_ofrece_reintentar_sobre_el_mismo_pedido(): void
    {
        $order = $this->storeOrder(['id' => 'ord-7', 'status' => 'pending_payment', 'statusLabel' => 'Pago pendiente', 'paymentMethod' => 'mercado_pago', 'payment' => [
            'provider' => 'mercado_pago', 'status' => 'rejected', 'redirectUrl' => null,
            'rejection' => ['reason' => 'insufficient_funds', 'message' => 'Tu tarjeta no tiene fondos.', 'retryable' => true],
        ]]);

        $content = $this->visit(['id' => 'ord-7', 'method' => 'mercado_pago'], Http::response(['data' => $order]))
            ->assertOk()
            ->assertSee('No pudimos cobrar tu pago')
            ->assertSee('Tu tarjeta no tiene fondos.')
            ->assertSee('Tu pedido se conserva: solo se cobra de nuevo.')
            ->assertSee('<script defer src="https://www.mercadopago.com/v2/security.js" view="checkout"></script>', false)
            // Something is still pending here: the pointer stays unmarked.
            ->assertCookieMissing('sf_order')
            ->assertDontSee('order-status__folio', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/data-retry-form\s+data-order="ord-7"/', $content);
        // Two submits of the same form: the mobile bar and the desktop column.
        $this->assertSame(2, preg_match_all('/type="submit">\s*<span>Reintentar el pago<\/span>/', $content));
    }

    #[Test]
    public function mientras_llega_el_webhook_se_relee_la_pantalla(): void
    {
        $order = $this->storeOrder(['status' => 'pending_payment', 'statusLabel' => 'Pago pendiente', 'paymentMethod' => 'mercado_pago', 'payment' => ['provider' => 'mercado_pago', 'status' => 'pending', 'redirectUrl' => null]]);

        $content = $this->visit(['id' => 'ord-1', 'method' => 'mercado_pago', 'chargeStarted' => true], Http::response(['data' => $order]), '/mamayaya/recibido?order=ord%201')
            ->assertOk()
            ->assertSee('Estamos confirmando tu pago')
            ->assertSee('href="/mamayaya/recibido?order=ord%201"', false)
            ->assertDontSee('Ver estado de pedido')
            ->getContent();

        $this->assertMatchesRegularExpression('/screen-body--confirmation"\s+data-awaiting-payment=""/', $content);
    }

    #[Test]
    public function si_la_lectura_falla_se_sirve_con_lo_que_haya(): void
    {
        $this->visit(['id' => 'ord-1', 'method' => 'mercado_pago'], Http::response('Server Error', 500), draft: ['tip' => 15])
            ->assertOk()
            ->assertSee('Pedido confirmado')
            // Without an order the cart is listed, with the draft's tip: 575 + 15.
            ->assertSee('<span class="order-receipt__name">Tacos al pastor</span>', false)
            ->assertSee('<span class="order-receipt__total">$590.00</span>', false)
            // The mockup's folio, and no message: there's no order to tell about.
            ->assertSee('<p class="order-status__folio">#91</p>', false)
            ->assertDontSee('wa.me/529981112233?text=', false)
            ->assertCookieMissing('sf_order');
    }

    #[Test]
    public function sin_telefono_el_boton_queda_inerte_y_sin_segunda_accion(): void
    {
        // The phone falls back to config/store-fallback.php: empty there too.
        config(['store-fallback.whatsapp.phone' => '']);

        $content = $this->visit(['id' => 'ord-1', 'method' => 'bank_transfer'], store: ['whatsapp' => ['phone' => '', 'templates' => self::TEMPLATES]])
            ->assertOk()
            ->assertDontSee('Ver estado de pedido')
            ->getContent();

        $this->assertMatchesRegularExpression('/<button class="action-bar__button btn btn-primary action-bar__button--whatsapp" type="submit">/', $content);
    }

    #[Test]
    public function en_modo_de_pruebas_se_revisa_sin_pedido(): void
    {
        config(['shop.test_mode' => true]);

        $this->visit()
            ->assertOk()
            ->assertSee('Pedido recibido')
            ->assertSee('Realiza tu transferencia bancaria');
    }

    #[Test]
    public function la_vuelta_vieja_de_mercado_pago_conserva_el_pedido(): void
    {
        $this->get('/mamayaya/confirmado?order=ord-1')->assertRedirect('/mamayaya/recibido?order=ord-1');
    }
}
