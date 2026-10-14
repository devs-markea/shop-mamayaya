<?php

namespace Tests\Feature\Support;

use App\Support\CheckoutDraft;
use App\Support\Shipping;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShippingTest extends TestCase
{
    private const THRESHOLD = ['mode' => 'threshold', 'threshold' => 400];

    private function quote(array $overrides = []): array
    {
        return Shipping::parseQuote([...['cost' => 45, 'km' => 3.3999999, 'quoteId' => 'q1', 'serviceable' => true], ...$overrides]);
    }

    #[Test]
    public function resuelve_el_envio_en_su_orden(): void
    {
        $this->assertSame('none', Shipping::resolve(true, $this->quote(), 500, self::THRESHOLD)['state']);
        // Out of area wins over free shipping: it can't promise a delivery it won't make.
        $this->assertSame('pending', Shipping::resolve(false, $this->quote(['serviceable' => false]), 500, self::THRESHOLD)['state']);
        $this->assertSame('free', Shipping::resolve(false, $this->quote(), 400, self::THRESHOLD)['state']);
        $this->assertSame(['state' => 'quoted', 'cost' => 45, 'km' => 3.4], Shipping::resolve(false, $this->quote(), 399, self::THRESHOLD));
        $this->assertSame('pending', Shipping::resolve(false, null, 100, self::THRESHOLD)['state']);
        $this->assertSame('free', Shipping::resolve(false, null, 1, ['mode' => 'always', 'threshold' => null])['state']);
    }

    #[Test]
    public function descarta_cotizaciones_que_no_son_de_fiar(): void
    {
        $this->assertNull(Shipping::parseQuote('x'));
        $this->assertNull($this->quote(['cost' => -5])['cost']);
        $this->assertNull($this->quote(['cost' => '45'])['cost']);
    }

    #[Test]
    public function el_aviso_de_fuera_de_zona_tiene_respaldo(): void
    {
        $quote = Shipping::quoteFromResponse(['quoteId' => 'q', 'serviceable' => false, 'message' => null]);

        $this->assertSame(Shipping::AREA_NOTICE_FALLBACK, $quote['notice']);
    }

    #[Test]
    public function rotula_el_avance_hacia_el_envio_gratis(): void
    {
        $this->assertSame(81, Shipping::progress(325, 400));
        $this->assertSame(100, Shipping::progress(500, 400));
        $this->assertSame(75, Shipping::remainingForFreeShipping(325, 400));
        $this->assertSame("Estas a \$75.00 de obtener envio gratis \u{1F389}", Shipping::freeShippingLabel(75));
    }

    #[Test]
    public function valida_el_borrador_campo_a_campo(): void
    {
        $draft = CheckoutDraft::parse(json_encode([
            'deliveryType' => 'drone',
            'tip' => -100,
            'customer' => ['name' => 'Ana'],
            'shipping' => ['cost' => 'mucho'],
            'comments' => ['no', 'es', 'texto'],
        ]));

        $this->assertSame('delivery', $draft['deliveryType']);
        $this->assertSame(0, $draft['tip']);
        $this->assertSame(['name' => 'Ana', 'phone' => ''], $draft['customer']);
        $this->assertNull($draft['shipping']['cost']);
        $this->assertSame('', $draft['comments']);
        $this->assertSame(30, CheckoutDraft::parseTipAmount('30'));
        $this->assertNull(CheckoutDraft::parseTipAmount(12.5));
        $this->assertFalse(CheckoutDraft::hasSharedLocation($draft));
    }
}
