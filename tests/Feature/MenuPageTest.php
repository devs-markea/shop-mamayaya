<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesShopApi;
use Tests\TestCase;

class MenuPageTest extends TestCase
{
    use FakesShopApi;

    #[Test]
    public function pinta_el_menu_por_secciones_en_el_orden_del_panel(): void
    {
        $this->fakeShopApi();

        $response = $this->get('/mamayaya')->assertOk();

        // Featured first (by position), then the categories in the panel's order.
        $response->assertSeeInOrder(['id="menu-destacados"', 'Agua de horchata', 'Tacos al pastor', 'id="menu-categoria-2"', 'id="menu-categoria-1"'], false);
        $response->assertSee('<span class="product-card__rank">#1 favoritos</span>', false);

        // Prices and the promotion, computed by the API and only formatted here.
        $response->assertSee('$103.20');
        $response->assertSee('<s class="price-was">$129.00</s>', false);
        $response->assertSee('Envio gratis desde $400.00');

        // The placeholder image counts as no photo: no box is painted.
        $response->assertSee('menu-card card menu-card--no-media', false);

        // The search text is normalized like the browser normalizes the query.
        $response->assertSee('data-search="agua de horchata descripcion de agua de horchata bebidas"', false);
    }

    #[Test]
    public function el_buscador_movil_va_bajo_las_tabs_y_se_oculta_con_ellas(): void
    {
        $this->fakeShopApi();

        $html = $this->get('/mamayaya')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-menu-search'));
        // Inside the sliding bar, below the tabs: hiding the bar hides the field too.
        $this->assertMatchesRegularExpression(
            '/category-tabs--hidden.*category-tabs__bar.*category-tabs__row.*category-tabs__search.*data-menu-search/s',
            $html,
        );
        // Only the desktop field takes the keyboard shortcut.
        $this->assertSame(1, substr_count($html, 'aria-keyshortcuts'));
    }

    #[Test]
    public function usa_los_textos_y_el_respaldo_de_la_tienda(): void
    {
        $this->fakeShopApi();

        $this->get('/mamayaya')
            ->assertOk()
            ->assertSee('<title>Tienda de Prueba</title>', false)
            ->assertSee('Destacados')
            ->assertSee('Los favoritos de nuestros clientes')
            // The business's message is HTML rewritten by the backend: injected as is.
            ->assertSee('<strong>Recoge</strong> en el local.', false)
            // Unknown networks are dropped; labelled ones keep the network's name for screen readers.
            ->assertSee('Nuestro Insta')
            ->assertDontSee('MySpace')
            ->assertSee('© '.now('America/Cancun')->year.' <strong>Tienda de Prueba</strong>.', false);
    }

    #[Test]
    public function sin_catalogo_ensena_el_aviso_con_su_codigo(): void
    {
        $this->fakeShopApi(products: Http::response('Service Unavailable', 503));

        $this->get('/mamayaya')
            ->assertOk()
            ->assertSee('No pudimos cargar el menu en este momento.')
            ->assertSee('Codigo SF-M503')
            ->assertSee('data-retry-mode="auto"', false);
    }

    #[Test]
    public function con_un_429_no_se_recarga_sola_y_dice_cuanto_falta(): void
    {
        $this->fakeShopApi(products: Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '90']));

        $this->get('/mamayaya')
            ->assertOk()
            ->assertSee('La tienda esta recibiendo muchas visitas. El menu vuelve en 2 minutos.')
            ->assertSee('data-retry-mode="manual"', false);
    }

    #[Test]
    public function con_la_api_caida_se_sirve_con_el_respaldo_sin_esperar_tres_plazos(): void
    {
        Http::fake(['*' => Http::failedConnection('cURL error 28: Operation timed out')]);

        $this->get('/mamayaya')
            ->assertOk()
            // The fallback's name, and the timeout code for the catalog.
            ->assertSee('<title>Mamá Yaya</title>', false)
            ->assertSee('Codigo SF-MTMO');

        // Only the first read tried (three attempts); the rest failed at once.
        Http::assertSentCount(3);
    }

    #[Test]
    public function con_la_tienda_cerrada_avisa_y_apaga_el_mas(): void
    {
        $closed = [
            ...$this->openSchedule(),
            'isOpen' => false,
            'closesAt' => null,
            'opensAt' => ['dayOfWeek' => 1, 'time' => '09:00', 'inMinutes' => 3 * 1440],
        ];

        $this->fakeShopApi(schedule: Http::response(['data' => $closed]));

        $this->get('/mamayaya')
            ->assertOk()
            ->assertSee("Regresamos a las 09:00\u{00A0}h.")
            ->assertSee('Abrimos el lunes a las 09:00')
            ->assertSee('product-card__add product-card__add--disabled', false)
            ->assertSee('CERRADO POR HOY');
    }

    #[Test]
    public function sin_sesion_no_crea_un_carrito_para_ensenar_un_cero(): void
    {
        $this->fakeShopApi();

        $this->get('/mamayaya')->assertOk()->assertSee('Mi pedido, vacio');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/api/cart'));
    }

    #[Test]
    public function con_sesion_ensena_su_carrito(): void
    {
        $this->fakeShopApi(more: [
            'erp.test/api/cart' => Http::response(['data' => ['total' => 238.5, 'itemsCount' => 3, 'lines' => []]]),
        ]);

        $this->withUnencryptedCookie('sf_cart', '01JAAAAAAAAAAAAAAAAAAAAAAA')
            ->get('/mamayaya')
            ->assertOk()
            ->assertSee('Mi pedido: 3 articulos, $238.50')
            ->assertSee('Mi pedido: 3 articulos"', false);

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Cart-Token', '01JAAAAAAAAAAAAAAAAAAAAAAA'));
    }

    #[Test]
    public function el_switch_llega_con_el_modo_del_borrador(): void
    {
        $this->fakeShopApi();

        $draft = rawurlencode(json_encode(['deliveryType' => 'pickup']));

        $this->withUnencryptedCookie('sf_checkout', $draft)
            ->get('/mamayaya')
            ->assertOk()
            ->assertSee('id="delivery-pickup" value="recoger" checked', false)
            ->assertSee('data-delivery="pickup"', false);
    }
}
