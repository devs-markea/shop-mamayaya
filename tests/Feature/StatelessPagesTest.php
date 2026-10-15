<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesCarts;
use Tests\Concerns\FakesShopApi;
use Tests\TestCase;

/**
 * The pages carry no session (bootstrap/app.php), like the Astro store: no session file,
 * no session cookie and no CSRF cookie on any visit.
 */
class StatelessPagesTest extends TestCase
{
    use FakesCarts;
    use FakesShopApi;

    #[Test]
    public function las_paginas_no_abren_sesion(): void
    {
        $this->fakeShopApi(more: ['erp.test/api/cart' => Http::response(['data' => $this->cart()])]);

        $this->withUnencryptedCookie('sf_cart', '01JAAAAAAAAAAAAAAAAAAAAAAA')
            ->get('/mamayaya/carrito')
            ->assertOk()
            ->assertCookieMissing(config('session.cookie'))
            ->assertCookieMissing('XSRF-TOKEN');
    }

    #[Test]
    public function el_carrito_sigue_naciendo_con_su_propia_cookie(): void
    {
        $this->fakeShopApi(more: ['erp.test/api/cart' => Http::response(['data' => $this->cart()])]);

        // Without a cart token the cart issues one, the only cookie a page writes.
        $response = $this->get('/mamayaya/carrito')->assertOk();

        $this->assertNotNull($response->getCookie('sf_cart', decrypt: false));
        $response->assertCookieMissing(config('session.cookie'));
    }

    #[Test]
    public function un_envio_sin_javascript_no_encuentra_ruta(): void
    {
        // The forms are sent by JavaScript to /api/*. A native submit lands on the page's
        // own URL, which only answers GET: 405, and no longer the 419 of a missing token.
        $this->post('/mamayaya/datos')->assertStatus(405);
    }
}
