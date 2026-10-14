<?php

namespace Tests\Feature\Api;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopProxyTest extends TestCase
{
    private const TOKEN = '01JAAAAAAAAAAAAAAAAAAAAAAA';

    #[Test]
    public function la_primera_visita_estrena_la_sesion_del_carrito(): void
    {
        Http::fake(['erp.test/api/cart' => Http::response(['data' => ['id' => 'c1']])]);

        $response = $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.id', 'c1');

        $cookie = $response->getCookie('sf_cart', decrypt: false);

        $this->assertNotNull($cookie);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Cart-Token', $cookie->getValue()));
    }

    #[Test]
    public function con_sesion_reusa_su_token_e_ignora_el_del_navegador(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);

        $response = $this->withCredentials()->withUnencryptedCookie('sf_cart', self::TOKEN)
            ->getJson('/api/cart', ['X-Cart-Token' => 'el-de-otra-persona']);

        $this->assertNull($response->getCookie('sf_cart', decrypt: false));

        Http::assertSent(fn (Request $request) => $request->header('X-Cart-Token') === [self::TOKEN]);
    }

    #[Test]
    public function el_checkout_sube_el_cuerpo_y_solo_sus_dos_cabeceras(): void
    {
        Http::fake(['erp.test/api/checkout' => Http::response(['data' => ['id' => 'o1']], 201)]);

        $this->withCredentials()->withUnencryptedCookie('sf_cart', self::TOKEN)
            ->postJson('/api/checkout', ['paymentMethod' => 'cash'], [
                'Idempotency-Key' => 'k-1',
                'X-Meli-Session-Id' => 'meli-1',
                'X-Forwarded-For' => '6.6.6.6',
            ])
            ->assertStatus(201);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->hasHeader('Idempotency-Key', 'k-1')
            && $request->hasHeader('X-Meli-Session-Id', 'meli-1')
            && ! $request->hasHeader('X-Forwarded-For')
            && $request['paymentMethod'] === 'cash');
    }

    #[Test]
    public function devuelve_el_fallo_de_la_api_tal_cual(): void
    {
        Http::fake(['*' => Http::response(['message' => 'x', 'errors' => ['quantity' => ['Maximo 10.']]], 422)]);

        $this->withCredentials()->withUnencryptedCookie('sf_cart', self::TOKEN)
            ->patchJson('/api/cart/items/abc', ['quantity' => 99])
            ->assertStatus(422)
            ->assertJsonPath('errors.quantity.0', 'Maximo 10.')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    #[Test]
    public function reenvia_el_retry_after_de_un_429(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '30'])]);

        $this->withCredentials()->withUnencryptedCookie('sf_cart', self::TOKEN)
            ->postJson('/api/cart/items', ['productId' => 'p1', 'quantity' => 1])
            ->assertStatus(429)
            ->assertHeader('Retry-After', '30');
    }

    #[Test]
    public function sin_respuesta_de_la_api_responde_502(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $this->withCredentials()->withUnencryptedCookie('sf_cart', self::TOKEN)
            ->postJson('/api/store/phones', ['phone' => '9981234567'])
            ->assertStatus(502)
            ->assertJsonPath('message', 'No pudimos contactar con la tienda.');
    }
}
