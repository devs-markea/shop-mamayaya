<?php

namespace Tests\Feature\Support;

use App\Support\CheckoutDraft;
use App\Support\Clabe;
use App\Support\Shipping;
use App\Support\Tips;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The payment screens' rules. The expected values were produced by the Astro project's
 * own functions (normalizeTipAmounts, isCustomTip, formatClabe and the es-MX km
 * formatter), so these cases also pin the parity with the browser.
 */
class PaymentSupportTest extends TestCase
{
    #[Test]
    public function normaliza_los_importes_de_propina(): void
    {
        $this->assertSame([0, 5, 10, 11, 15], Tips::normalizeAmounts([5, 10, 11, 15]));
        $this->assertSame([0, 15, 30], Tips::normalizeAmounts([0, 15, 30]));
        $this->assertSame([0, 5, 10, 15], Tips::normalizeAmounts([15, 5, 15, 'x', 10]));
        $this->assertSame([0, 7, 8, 99999], Tips::normalizeAmounts([-5, 2.5, '7', ' 8 ', '', null, true, 100000, 99999]));
        $this->assertSame([0, 1, 3], Tips::normalizeAmounts([3, 3, 1]));
        // Without a positive amount there's no selector, not even a lonely 0.
        $this->assertSame([], Tips::normalizeAmounts([]));
        $this->assertSame([], Tips::normalizeAmounts([0]));
        $this->assertSame([], Tips::normalizeAmounts('nope'));
    }

    #[Test]
    public function distingue_la_propina_escrita_en_otro(): void
    {
        $this->assertTrue(Tips::isCustom(23, [0, 5]));
        $this->assertFalse(Tips::isCustom(5, [0, 5]));
        $this->assertFalse(Tips::isCustom(0, [0, 5]));
        $this->assertTrue(Tips::isCustom(7, []));
    }

    #[Test]
    public function rotula_los_kilometros_como_el_navegador(): void
    {
        $cases = [
            '0 km' => 0, '0.1 km' => 0.1, '1 km' => 1, '3.2 km' => 3.2, '10 km' => 10, '999.9 km' => 999.9,
            '1,000 km' => 1000, '1,234.5 km' => 1234.5, '12,345.6 km' => 12345.6, '2.3 km' => 2.25,
        ];

        foreach ($cases as $expected => $km) {
            $this->assertSame($expected, Shipping::formatKm($km));
        }
    }

    #[Test]
    public function agrupa_la_clabe(): void
    {
        $this->assertSame('0121 8000 1234 567895', Clabe::format('012180001234567895'));
        $this->assertSame('0121 8000 1234 567895', Clabe::format('0121 8000 1234 567895'));
        $this->assertSame('1234 5678 9012 3', Clabe::format('1234567890123'));
        $this->assertSame('1234 5678', Clabe::format('12345678'));
        $this->assertSame('', Clabe::format(''));
    }

    #[Test]
    public function lee_el_puntero_del_pedido(): void
    {
        $this->assertNull(CheckoutDraft::parseOrderPointer(null));
        $this->assertNull(CheckoutDraft::parseOrderPointer('{roto'));
        $this->assertNull(CheckoutDraft::parseOrderPointer(json_encode(['id' => 'ord-1'])));
        $this->assertNull(CheckoutDraft::parseOrderPointer(json_encode(['id' => '', 'method' => 'efectivo'])));

        // Only valid values travel: an invented mode or a non-boolean flag is dropped.
        $this->assertSame(
            ['id' => 'ord-1', 'method' => 'mercado_pago', 'deliveryType' => 'pickup', 'chargeStarted' => false],
            CheckoutDraft::parseOrderPointer(json_encode(['id' => 'ord-1', 'method' => 'mercado_pago', 'deliveryType' => 'pickup', 'chargeStarted' => false, 'seen' => 'yes'])),
        );
        $this->assertSame(
            ['id' => 'ord-2', 'method' => 'efectivo', 'seen' => true],
            CheckoutDraft::parseOrderPointer(rawurlencode(json_encode(['id' => 'ord-2', 'method' => 'efectivo', 'deliveryType' => 'moto', 'seen' => true]))),
        );
    }

    #[Test]
    public function enumera_lo_que_le_falta_al_borrador(): void
    {
        $this->assertSame(
            ['tu nombre', 'tu telefono', 'la colonia', 'la calle', 'el numero exterior', 'tu ubicacion en el mapa'],
            CheckoutDraft::draftGaps(CheckoutDraft::parse(null)),
        );

        $pickup = CheckoutDraft::parse(json_encode(['deliveryType' => 'pickup', 'customer' => ['name' => ' Ana ', 'phone' => '(998) 111-22']]));
        $this->assertSame(['un telefono valido de 10 digitos'], CheckoutDraft::draftGaps($pickup));

        $delivery = CheckoutDraft::parse(json_encode(['customer' => [
            'name' => 'Ana', 'phone' => '998 111 2233', 'neighborhood' => 'Centro', 'street' => ' ', 'exteriorNumber' => '45',
            'locationUrl' => 'https://www.google.com/maps?q=21.16,-86.85',
        ]]));
        $this->assertSame(['la calle'], CheckoutDraft::draftGaps($delivery));
    }
}
