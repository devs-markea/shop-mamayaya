<?php

namespace Tests\Feature\Support;

use App\Services\ShopApi\ShopApiException;
use App\Support\Chat;
use App\Support\CheckoutDraft;
use App\Support\ErrorCodes;
use App\Support\Price;
use App\Support\Search;
use App\Support\WhatsApp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class FormattingTest extends TestCase
{
    /** The browser's Intl.NumberFormat('es-MX', MXN) output for the same values. */
    public static function prices(): array
    {
        return [[0, '$0.00'], [0.8, '$0.80'], [129, '$129.00'], [1234.5, '$1,234.50'], [1000000, '$1,000,000.00'], [-5, '-$5.00'], [12.345, '$12.35']];
    }

    #[Test]
    #[DataProvider('prices')]
    public function formatea_precios_como_el_navegador(float|int $value, string $expected): void
    {
        $this->assertSame($expected, Price::format($value));
    }

    #[Test]
    public function normaliza_la_busqueda_como_el_navegador(): void
    {
        // The browser's normalizeSearch() gives exactly this for the same text.
        $this->assertSame('poke de atun nandu u', Search::normalize("Pokè  de Atún\tÑandú ü"));
    }

    #[Test]
    public function arma_el_enlace_de_whatsapp(): void
    {
        $this->assertSame('529987566999', WhatsApp::normalizePhone('+52 1 998 756 6999'));
        $this->assertNull(WhatsApp::url(''));
        $this->assertSame(
            'https://wa.me/529987566999?text=%C2%A1Hola!%20Necesito%20ayuda%20para%20hacer%20un%20pedido%20en%20l%C3%ADnea.%20',
            WhatsApp::url(Chat::PHONE, Chat::message()),
        );
    }

    #[Test]
    public function compone_los_codigos_de_error(): void
    {
        $this->assertSame('SF-M503', ErrorCodes::fromException(ErrorCodes::MENU, new ShopApiException('x', 503, '/api/products')));
        $this->assertSame('SF-MTMO', ErrorCodes::fromException(ErrorCodes::MENU, new ShopApiException('x', 0, '/', timedOut: true)));
        $this->assertSame('SF-MNET', ErrorCodes::fromException(ErrorCodes::MENU, new ShopApiException('x', 0, '/')));
        $this->assertSame('SF-MERR', ErrorCodes::fromException(ErrorCodes::MENU, new RuntimeException('x')));
    }

    #[Test]
    public function lee_el_modo_de_entrega_del_borrador(): void
    {
        $this->assertSame('pickup', CheckoutDraft::deliveryType('{"deliveryType":"pickup"}'));
        $this->assertSame('pickup', CheckoutDraft::deliveryType(rawurlencode('{"deliveryType":"pickup"}')));
        $this->assertSame('delivery', CheckoutDraft::deliveryType('{"deliveryType":"drone"}'));
        $this->assertSame('delivery', CheckoutDraft::deliveryType('no es json'));
        $this->assertSame('delivery', CheckoutDraft::deliveryType(null));
    }
}
