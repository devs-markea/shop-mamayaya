<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesCarts;
use Tests\Concerns\FakesShopApi;
use Tests\TestCase;

class PaymentPagesTest extends TestCase
{
    use FakesCarts;
    use FakesShopApi;

    private const BANK = ['holder' => 'Tienda de Prueba SA', 'bank' => 'BBVA', 'clabe' => '012180001234567895'];

    /** A delivery draft with everything cash needs: contact, address and a shared point. */
    private const COMPLETE_DRAFT = [
        'customer' => [
            'name' => 'Ana', 'phone' => '9981112233', 'neighborhood' => 'Centro', 'street' => 'Coba',
            'exteriorNumber' => '45', 'locationUrl' => 'https://www.google.com/maps?q=21.16,-86.85',
        ],
        'shipping' => ['cost' => 45, 'km' => 3.2, 'quoteId' => 'q1', 'serviceable' => true],
    ];

    private function visit(string $path, mixed $cart = null, ?array $draft = null, array $store = [], array $cookies = [])
    {
        $this->fakeShopApi(
            store: Http::response(['data' => $this->store([
                'bankTransfer' => self::BANK,
                'tips' => ['enabled' => true, 'amounts' => [15, 5, 15, 'x', 10]],
                'legal' => ['termsUrl' => 'https://example.com/terminos'],
                // Without free shipping, so the shipping row shows its pending and quoted states.
                'delivery' => ['freeShipping' => ['mode' => 'none', 'threshold' => null]],
                ...$store,
            ])]),
            more: ['erp.test/api/cart' => $cart ?? Http::response(['data' => $this->fullCart()])],
        );

        $request = $this->withUnencryptedCookie('sf_cart', '01JAAAAAAAAAAAAAAAAAAAAAAA');

        if ($draft !== null) {
            $request = $request->withUnencryptedCookie('sf_checkout', rawurlencode(json_encode($draft)));
        }

        foreach ($cookies as $name => $value) {
            $request = $request->withUnencryptedCookie($name, $value);
        }

        return $request->get($path);
    }

    private function fullCart(array $overrides = []): array
    {
        return $this->cart([
            'items' => [
                $this->cartItem(),
                $this->cartItem([
                    'id' => 'b2', 'name' => 'Poke "Milanesa"', 'variantName' => null, 'options' => [], 'quantity' => 1,
                    'image' => ['url' => '', 'alt' => ''], 'lineTotal' => 80,
                    'promotion' => ['id' => '1', 'name' => 'Promo 20', 'type' => 'percentage', 'source' => 'own', 'label' => '20%'],
                ]),
            ],
            ...$overrides,
        ]);
    }

    private function emptyCart(): mixed
    {
        return Http::response(['data' => $this->cart(['itemsCount' => 0, 'lines' => [], 'items' => [], 'subtotal' => 0, 'discountTotal' => 0, 'total' => 0])]);
    }

    #[Test]
    public function el_resumen_ofrece_propina_metodos_y_total(): void
    {
        $content = $this->visit('/mamayaya/pago')
            ->assertOk()
            ->assertSee('<title>Resumen de pago — Tienda de Prueba</title>', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow" />', false)
            ->assertSee('<script defer src="https://www.mercadopago.com/v2/security.js" view="checkout"></script>', false)
            ->assertSee('<a class="legal-note__link" href="https://example.com/terminos" target="_blank" rel="noopener">Terminos y condiciones</a>', false)
            ->assertSee('Confirmar el pago</button>', false)
            ->getContent();

        // Normalized: the 0 in front, sorted, without duplicates nor junk.
        preg_match_all('/id="tip-(\d+)"/', $content, $tips);
        $this->assertSame(['0', '5', '10', '15'], $tips[1]);

        // Without a draft the tip is offered, not applied: the 0 comes checked.
        $this->assertMatchesRegularExpression('/id="tip-0"\s+value="0"\s+checked\s+autocomplete="off"\s+aria-label="Sin propina"/', $content);
        $this->assertMatchesRegularExpression('/id="tip-custom"\s+value=""\s+autocomplete="off"/', $content);

        // Delivery with a published account: the transfer comes checked, then Mercado Pago.
        $this->assertMatchesRegularExpression('/id="payment-transfer"\s+value="transferencia"\s+checked/', $content);
        $this->assertMatchesRegularExpression('/id="payment-mercadopago"\s+value="mercadopago"\s+autocomplete/', $content);
        $this->assertStringNotContainsString('id="payment-cash"', $content);
        $this->assertStringContainsString('class="payment-option__media payment-option__media--logo"', $content);

        // Without a shared location the shipping is pending and adds nothing.
        $this->assertStringContainsString('data-summary-total>$575.00</dd>', $content);
        $this->assertMatchesRegularExpression('/data-shipping="0"\s+data-shipping-state="pending"/', $content);
    }

    #[Test]
    public function el_resumen_repone_la_propina_escrita_en_otro(): void
    {
        $content = $this->visit('/mamayaya/pago', draft: [...self::COMPLETE_DRAFT, 'tip' => 23, 'paymentMethod' => 'mercado_pago'])
            ->assertOk()
            ->assertSee('<span class="summary__hint">3.2 km</span>', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/id="tip-custom"\s+value="23"\s+checked/', $content);
        $this->assertMatchesRegularExpression('/id="tip-amount"[^>]*value="23"/s', $content);
        $this->assertMatchesRegularExpression('/id="payment-mercadopago"\s+value="mercadopago"\s+checked/', $content);

        // 575 + 45 of shipping + 23 of tip.
        $this->assertStringContainsString('data-summary-total>$643.00</dd>', $content);
        $this->assertStringContainsString('data-cart-total>$643.00</dd>', $content);
    }

    #[Test]
    public function al_recoger_solo_hay_efectivo(): void
    {
        $content = $this->visit('/mamayaya/pago', draft: ['deliveryType' => 'pickup', 'paymentMethod' => 'bank_transfer'])
            ->assertOk()
            ->assertSee('Pagas al recoger tu pedido en el local')
            ->assertDontSee('<span class="summary__hint">', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/id="payment-cash"\s+value="efectivo"\s+checked/', $content);
        $this->assertDoesNotMatchRegularExpression('/<dt class="summary__term">\s*Costo de envio/', $content);
        $this->assertStringNotContainsString('id="payment-transfer"', $content);
        $this->assertStringNotContainsString('id="payment-mercadopago"', $content);
    }

    #[Test]
    public function sin_cuenta_publicada_no_se_ofrece_la_transferencia(): void
    {
        $content = $this->visit('/mamayaya/pago', store: ['bankTransfer' => ['holder' => 'Solo titular']], draft: ['paymentMethod' => 'bank_transfer'])
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="payment-transfer"', $content);
        $this->assertMatchesRegularExpression('/id="payment-mercadopago"\s+value="mercadopago"\s+checked/', $content);
    }

    #[Test]
    public function con_la_propina_apagada_no_hay_selector(): void
    {
        $this->visit('/mamayaya/pago', store: ['tips' => ['enabled' => false, 'amounts' => [5, 10]]])
            ->assertOk()
            ->assertDontSee('name="tip"', false);
    }

    #[Test]
    public function el_cobro_rechazado_vuelve_al_acuse(): void
    {
        Http::fake();

        $this->get('/mamayaya/pago?order=ord%2F1&pago=rechazado')->assertRedirect('/mamayaya/recibido?order=ord%2F1');

        Http::assertNothingSent();
    }

    #[Test]
    public function el_carrito_vacio_tras_pagar_lleva_al_acuse(): void
    {
        $pointer = json_encode(['id' => 'ord-1', 'method' => 'bank_transfer']);

        $this->visit('/mamayaya/pago', cart: $this->emptyCart(), cookies: ['sf_order' => $pointer])
            ->assertRedirect('/mamayaya/recibido');
    }

    #[Test]
    public function con_el_acuse_ya_visto_lleva_al_menu(): void
    {
        $seen = json_encode(['id' => 'ord-1', 'method' => 'bank_transfer', 'seen' => true]);

        $this->visit('/mamayaya/pago', cart: $this->emptyCart(), cookies: ['sf_order' => $seen])
            ->assertRedirect('/mamayaya');
    }

    #[Test]
    public function sin_pedido_cerrado_el_carrito_vacio_vuelve_al_pedido(): void
    {
        $this->visit('/mamayaya/pago', cart: $this->emptyCart())->assertRedirect('/mamayaya/carrito');
    }

    #[Test]
    public function con_la_api_caida_el_resumen_se_sirve(): void
    {
        $this->visit('/mamayaya/pago', cart: Http::response('Bad Gateway', 502))
            ->assertOk()
            ->assertSee('data-payment-form', false)
            ->assertSee('data-products="0"', false);
    }

    #[Test]
    public function la_transferencia_ensena_la_cuenta_y_el_detalle(): void
    {
        $content = $this->visit('/mamayaya/pago/transferencia', draft: ['tip' => 10])
            ->assertOk()
            ->assertSee('<title>Pago por transferencia — Tienda de Prueba</title>', false)
            ->assertSee('<span class="bank-account__value bank-account__value--clabe">0121 8000 1234 567895</span>', false)
            ->assertSee('aria-label="Copiar la CLABE" data-copy="012180001234567895"', false)
            ->assertSee('Al finalizar tu pedido se te indicara el costo de envio exacto.')
            ->assertSee('<span class="order-detail__name">2× Tacos al pastor</span>', false)
            ->assertSee('<span class="order-detail__custom">Orden grande - Roja - Queso x2</span>', false)
            ->assertSee('<span class="order-detail__name">1× Poke &quot;Milanesa&quot;</span>', false)
            ->assertSee('<span class="promo-badge__label">20%</span>', false)
            ->assertSee('<dd class="order-detail__value">− $120.00</dd>', false)
            ->assertSee('<dd class="order-detail__value order-detail__value--pending">Por cotizar</dd>', false)
            ->assertSee('Confirmar pedido</button>', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/data-confirm-form\s+data-method="bank_transfer"\s+data-next="\/mamayaya\/recibido"\s+data-shipping="0"/', $content);
        // 575 + 10 of tip; the pending shipping adds nothing.
        $this->assertStringContainsString('<span class="order-detail__total-value">$585.00</span>', $content);
        // Without a photo there's no box: only the first line has one.
        $this->assertSame(1, substr_count($content, 'class="order-detail__image"'));
    }

    #[Test]
    public function con_el_envio_medido_la_transferencia_es_definitiva(): void
    {
        $this->visit('/mamayaya/pago/transferencia', draft: self::COMPLETE_DRAFT)
            ->assertOk()
            ->assertSee('Realiza la transferencia por el importe total y envianos el comprobante para confirmar tu pedido.')
            ->assertSee('<span class="order-detail__hint">3.2 km</span>', false)
            ->assertSee('<span class="order-detail__total-value">$620.00</span>', false);
    }

    #[Test]
    public function al_recoger_la_transferencia_vuelve_al_resumen_sin_llamar_a_la_api(): void
    {
        Http::fake();

        $this->withUnencryptedCookie('sf_checkout', rawurlencode(json_encode(['deliveryType' => 'pickup'])))
            ->get('/mamayaya/pago/transferencia')
            ->assertRedirect('/mamayaya/pago');

        Http::assertNothingSent();
    }

    #[Test]
    public function la_transferencia_sin_cuenta_vuelve_al_resumen(): void
    {
        $this->visit('/mamayaya/pago/transferencia', store: ['bankTransfer' => null])
            ->assertRedirect('/mamayaya/pago');
    }

    #[Test]
    public function la_transferencia_sin_pedido_vuelve_al_carrito(): void
    {
        $this->visit('/mamayaya/pago/transferencia', cart: $this->emptyCart())
            ->assertRedirect('/mamayaya/carrito');
    }

    #[Test]
    public function el_efectivo_sin_pedido_vuelve_al_carrito(): void
    {
        // Even with the data missing: filling in an address creates no lines.
        $this->visit('/mamayaya/pago/efectivo', cart: $this->emptyCart())->assertRedirect('/mamayaya/carrito');
    }

    #[Test]
    public function el_efectivo_pide_los_datos_completos(): void
    {
        $this->visit('/mamayaya/pago/efectivo', draft: ['deliveryType' => 'pickup', 'customer' => ['name' => 'Ana', 'phone' => '998 111']])
            ->assertRedirect('/mamayaya/datos');
    }

    #[Test]
    public function el_efectivo_al_recoger_no_promete_envio(): void
    {
        $content = $this->visit('/mamayaya/pago/efectivo', draft: ['deliveryType' => 'pickup', 'tip' => 15, 'customer' => ['name' => 'Ana', 'phone' => '998 111 2233']])
            ->assertOk()
            ->assertSee('<title>Pago en efectivo — Tienda de Prueba</title>', false)
            ->assertSee('<span class="payment-method__note d-md-none">Efectivo en mano</span>', false)
            ->assertDontSee('Envio</dt>', false)
            ->assertSee('<span class="order-detail__total-value">$590.00</span>', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/<p class="info-note__text"\s*>Se recomienda preparar el monto exacto para facilitar la entrega\.<\/p>/', $content);
        $this->assertMatchesRegularExpression('/data-method="efectivo"\s+data-next="\/mamayaya\/recibido"\s+data-shipping="0"/', $content);
    }
}
