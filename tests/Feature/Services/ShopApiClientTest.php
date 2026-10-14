<?php

namespace Tests\Feature\Services;

use App\Services\ShopApi\ShopApiClient;
use App\Services\ShopApi\ShopApiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ShopApiClientTest extends TestCase
{
    private function client(): ShopApiClient
    {
        return $this->app->make(ShopApiClient::class);
    }

    #[Test]
    public function abre_el_sobre_y_se_identifica_como_la_tienda(): void
    {
        Http::fake(['erp.test/api/store' => Http::response(['data' => ['name' => 'Mamá Yaya']])]);

        $this->assertSame(['name' => 'Mamá Yaya'], $this->client()->get('/api/store'));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://erp.test/api/store'
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('X-Shop-Key', 'test-shop-key')
            && str_contains($request->header('User-Agent')[0], 'Chrome/'));
    }

    #[Test]
    public function quien_llama_no_puede_pisar_la_clave_ni_el_user_agent(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->client()->get('/api/store', ['X-Shop-Key' => 'otra', 'User-Agent' => 'node']);

        Http::assertSent(fn (Request $request) => $request->header('X-Shop-Key') === ['test-shop-key']
            && $request->header('User-Agent')[0] !== 'node');
    }

    #[Test]
    public function una_lectura_se_reintenta_ante_un_5xx(): void
    {
        Http::fakeSequence()
            ->push('Bad Gateway', 502)
            ->push(['data' => ['ok' => true]]);

        $this->assertSame(['ok' => true], $this->client()->get('/api/products'));

        Http::assertSentCount(2);
    }

    #[Test]
    public function el_429_no_se_reintenta_y_dice_cuanto_esperar(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '42'])]);

        try {
            $this->client()->get('/api/products');
            $this->fail('Se esperaba ShopApiException.');
        } catch (ShopApiException $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame(42, $e->retryAfter);
        }

        Http::assertSentCount(1);
    }

    #[Test]
    public function lo_que_cambia_estado_no_se_reintenta(): void
    {
        Http::fake(['*' => Http::response('Bad Gateway', 502)]);

        $response = $this->client()->fetch('POST', '/api/cart/items', [], '{}');

        $this->assertSame(502, $response->status());
        Http::assertSentCount(1);
    }

    #[Test]
    public function un_2xx_sin_json_es_un_fallo_con_su_status(): void
    {
        // La pagina del reto del antibots de SiteGround.
        Http::fake(['*' => Http::response('<html><meta http-equiv="refresh" content="0;/.well-known/sgcaptcha/?r=%2F&ipc=1.2.3.4">', 202)]);

        try {
            $this->client()->get('/api/products');
            $this->fail('Se esperaba ShopApiException.');
        } catch (ShopApiException $e) {
            $this->assertSame(202, $e->status);
            $this->assertStringContainsString('sin JSON', $e->getMessage());
            $this->assertStringContainsString('sgcaptcha', $e->getMessage());
        }
    }

    #[Test]
    public function un_422_trae_sus_errores(): void
    {
        Http::fake(['*' => Http::response(['message' => 'x', 'errors' => ['phone' => ['Diez digitos.']]], 422)]);

        try {
            $this->client()->get('/api/store');
            $this->fail('Se esperaba ShopApiException.');
        } catch (ShopApiException $e) {
            $this->assertSame(['Diez digitos.'], $e->body['errors']['phone']);
        }
    }

    #[Test]
    public function sin_respuesta_lanza_con_status_cero_tras_reintentar(): void
    {
        Http::fake(['*' => Http::failedConnection('cURL error 6: Could not resolve host')]);

        try {
            $this->client()->get('/api/store');
            $this->fail('Se esperaba ShopApiException.');
        } catch (ShopApiException $e) {
            $this->assertSame(0, $e->status);
            $this->assertFalse($e->timedOut);
        }

        Http::assertSentCount(3);
    }

    #[Test]
    public function un_plazo_agotado_se_distingue_de_no_llegar(): void
    {
        Http::fake(['*' => Http::failedConnection('cURL error 28: Operation timed out')]);

        try {
            $this->client()->get('/api/store');
            $this->fail('Se esperaba ShopApiException.');
        } catch (ShopApiException $e) {
            $this->assertTrue($e->timedOut);
            $this->assertStringContainsString('no respondio en', $e->getMessage());
        }
    }

    #[Test]
    public function conserva_el_subpath_de_la_base(): void
    {
        config(['services.shop_api.url' => 'https://host.test/tienda/']);
        $this->app->forgetInstance(ShopApiClient::class);

        $this->assertSame('https://host.test/tienda/api/products', $this->client()->endpoint('/api/products'));
        $this->assertSame('https://host.test/tienda/storage/a.jpg', $this->client()->assetUrl('/storage/a.jpg'));
        $this->assertSame('https://cdn.test/a.jpg', $this->client()->assetUrl('https://cdn.test/a.jpg'));
    }

    #[Test]
    public function sin_api_url_falla_nombrandola(): void
    {
        config(['services.shop_api.url' => null]);
        $this->app->forgetInstance(ShopApiClient::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API_URL');

        $this->client()->get('/api/store');
    }
}
