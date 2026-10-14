<?php

namespace Tests\Feature\Services;

use App\Services\Catalog\CatalogService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesShopApi;
use Tests\TestCase;

class CatalogServiceTest extends TestCase
{
    use FakesShopApi;

    private function catalog(): CatalogService
    {
        return $this->app->make(CatalogService::class);
    }

    #[Test]
    public function arma_las_secciones_por_id_y_en_el_orden_del_panel(): void
    {
        $items = [
            ...$this->products(),
            // A category the panel doesn't list: painted after, with the name it brings.
            $this->product('4', 'pastel', 'Pastel', '9', ['category' => ['id' => 9, 'name' => 'Postres', 'description' => 'Dulces']]),
        ];

        $sections = $this->catalog()->menuSections($items, [['id' => '2', 'name' => 'Aguas'], ['id' => '1', 'name' => 'Tacos'], ['id' => '7', 'name' => 'Vacia']]);

        $this->assertSame(['menu-destacados', 'menu-categoria-2', 'menu-categoria-1', 'menu-categoria-9'], array_column($sections, 'anchor'));
        // The panel's name wins for listed categories; the featured ones sort by position.
        $this->assertSame('Aguas', $sections[1]['name']);
        $this->assertSame(['agua-de-horchata', 'tacos-al-pastor'], array_column($sections[0]['products'], 'slug'));
        $this->assertSame('Dulces', $sections[3]['description']);
    }

    #[Test]
    public function sin_destacados_no_hay_seccion_de_destacados(): void
    {
        $sections = $this->catalog()->menuSections([$this->product('5', 'sopes', 'Sopes', '1')]);

        $this->assertSame(['category'], array_column($sections, 'kind'));
    }

    #[Test]
    public function el_horario_manda_sobre_el_available_guardado(): void
    {
        $stale = $this->product('6', 'tortas', 'Tortas', '1', ['available' => false, 'unavailableReason' => 'out_of_schedule']);
        $noPrice = $this->product('7', 'pozole', 'Pozole', '1', ['available' => false, 'unavailableReason' => 'no_price']);

        // Open store: a cached "out of schedule" is ignored.
        $this->assertTrue($this->catalog()->productCardView($stale, false)['orderable']);
        // Closed store: nothing is orderable and the reason stays quiet.
        $this->assertSame([false, null], array_values(array_intersect_key($this->catalog()->productCardView($stale, true), ['orderable' => 0, 'notice' => 0])));
        // Any other reason is respected.
        $this->assertSame('Sin precio', $this->catalog()->productCardView($noPrice, false)['notice']);
    }

    #[Test]
    public function recorta_el_nombre_y_resuelve_la_foto(): void
    {
        $view = $this->catalog()->productCardView($this->product('8', 'x', 'Hamburguesa doble con tocino y queso', '1'), false);

        $this->assertSame('Hamburguesa doble con to...', $view['displayName']);
        $this->assertSame('https://erp.test/storage/x.jpg', $view['image']['src']);
        $this->assertNull($this->catalog()->productImage(['image' => ['url' => '/images/placeholder-dish.svg?v=2']]));
        $this->assertSame('https://erp.test/storage/y.jpg', $this->catalog()->productImage(['image' => ['url' => '/storage/y.jpg']])['src']);
    }

    #[Test]
    public function decide_que_pinta_cada_promocion(): void
    {
        $buyGet = CatalogService::promotionView(['basePrice' => 10, 'promotion' => [
            'name' => '2x1', 'type' => 'buy_get', 'label' => '2x1', 'discount' => null, 'buyGet' => ['buy' => 2, 'get' => 1, 'free' => 1],
        ]]);

        $this->assertSame(['Lleva 2 y paga 1', null, 10], [$buyGet['detailLabel'], $buyGet['original'], $buyGet['price']]);

        $partial = CatalogService::promotionView(['basePrice' => 189, 'promotion' => [
            'name' => 'Especial', 'type' => 'discount', 'label' => '$150', 'buyGet' => null,
            'discount' => ['kind' => 'special', 'value' => 150, 'originalPrice' => 189, 'finalPrice' => 189, 'savings' => 0, 'variantsAffected' => 1, 'variantsTotal' => 3],
        ]]);

        // A `special` that doesn't lower the cheapest variant strikes nothing through.
        $this->assertNull($partial['original']);
        $this->assertSame('Precio especial de $150.00', $partial['detailLabel']);
        $this->assertSame('Precio especial en 1 de 3 tamanos', $partial['note']);
    }
}
