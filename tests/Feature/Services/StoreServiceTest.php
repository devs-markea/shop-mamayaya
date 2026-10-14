<?php

namespace Tests\Feature\Services;

use App\Services\Store\StoreService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesShopApi;
use Tests\TestCase;

class StoreServiceTest extends TestCase
{
    use FakesShopApi;

    private function settings(): array
    {
        return $this->app->make(StoreService::class)->settings();
    }

    #[Test]
    public function sin_api_usa_el_respaldo_y_no_lanza(): void
    {
        Http::fake(['*' => Http::response('Bad Gateway', 502)]);

        $settings = $this->settings();

        $this->assertSame('Mamá Yaya', $settings['name']);
        $this->assertSame(['mode' => 'threshold', 'threshold' => 400], $settings['delivery']['freeShipping']);
        // A half-filled account (empty in the fallback) is no account.
        $this->assertNull($settings['bankTransfer']);
        $this->assertSame([], $settings['whatsapp']['templates']);
    }

    #[Test]
    public function rellena_huecos_dato_a_dato(): void
    {
        $this->fakeShopApi(store: Http::response(['data' => ['name' => '  ', 'pickup' => ['estimate' => '5 min']]]));

        $settings = $this->settings();

        // An empty string from the panel is as unconfigured as a missing field.
        $this->assertSame('Mamá Yaya', $settings['name']);
        $this->assertSame('5 min', $settings['pickup']['estimate']);
        $this->assertSame('25-35 min', $settings['delivery']['estimate']);
    }

    #[Test]
    public function el_envio_gratis_apagado_no_cae_al_respaldo(): void
    {
        $this->fakeShopApi(store: Http::response(['data' => ['delivery' => ['freeShipping' => ['mode' => 'none', 'threshold' => null]]]]));

        $this->assertSame('none', $this->settings()['delivery']['freeShipping']['mode']);
    }

    #[Test]
    public function reduce_las_plantillas_al_contrato_vigente(): void
    {
        $this->fakeShopApi(store: Http::response(['data' => ['whatsapp' => ['phone' => '529981112233', 'templates' => [
            'delivery' => ['bank_transfer' => 'Transferencia'],
            'mercado_pago' => 'Plano de MP',
            'efectivo_pickup' => 'Efectivo al recoger',
            'paymentProof' => 'No se lee',
            'orderStatus' => '   ',
        ]]]]));

        $this->assertSame([
            'delivery' => ['bank_transfer' => 'Transferencia', 'mercado_pago' => 'Plano de MP'],
            'pickup' => ['efectivo' => 'Efectivo al recoger'],
        ], $this->settings()['whatsapp']['templates']);
    }

    #[Test]
    public function la_cuenta_bancaria_va_entera_o_no_va(): void
    {
        $this->fakeShopApi(store: Http::response(['data' => ['bankTransfer' => ['holder' => 'Prueba SA', 'bank' => '', 'clabe' => '000000000000000000']]]));

        $this->assertNull($this->settings()['bankTransfer']);
    }

    #[Test]
    public function el_horario_sin_api_es_null(): void
    {
        Http::fake(['*' => Http::response('Bad Gateway', 502)]);

        $this->assertNull($this->app->make(StoreService::class)->schedule());
    }
}
