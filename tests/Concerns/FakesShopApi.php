<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Http;

/**
 * Synthetic ERP responses for the screens' tests. They follow the API contract (the
 * `{data}` envelope, camelCase) with made-up data.
 */
trait FakesShopApi
{
    protected function store(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Tienda de Prueba',
            'socialLinks' => [
                ['network' => 'instagram', 'label' => 'Instagram', 'url' => 'https://instagram.com/prueba'],
                ['network' => 'myspace', 'label' => 'MySpace', 'url' => 'https://myspace.com/prueba'],
            ],
            'whatsapp' => ['phone' => '529981112233'],
            'location' => [
                'message' => '<strong>Recoge</strong> en el local.',
                'address' => 'Av. Siempre Viva 742',
                'shortAddress' => 'Av. Siempre Viva',
                'mapsUrl' => 'https://maps.app.goo.gl/prueba',
            ],
            'delivery' => ['freeShipping' => ['mode' => 'threshold', 'threshold' => 400], 'estimate' => '30-40 min'],
            'pickup' => ['estimate' => '15 min'],
            'home' => [
                'panel' => ['presentation' => null],
                'banner' => ['title' => 'Bienvenido', 'description' => 'Comida rica', 'image' => 'https://erp.test/storage/banner.jpg'],
                'featured' => ['title' => null, 'description' => null],
            ],
            'footer' => [
                'about' => ['title' => null, 'locationLabel' => null, 'schedule' => ['mode' => 'shift', 'label' => null], 'closedLabel' => null],
                'follow' => ['title' => null, 'labels' => ['instagram' => 'Nuestro Insta']],
                'promo' => ['title' => null, 'placeholder' => null, 'buttonLabel' => null],
                'copyright' => null,
            ],
            'categories' => [
                ['id' => '2', 'name' => 'Bebidas'],
                ['id' => '1', 'name' => 'Tacos'],
            ],
        ], $overrides);
    }

    /** Open every day from 00:00 to 23:59, so the store is open whenever the test runs. */
    protected function openSchedule(): array
    {
        return [
            'isOpen' => true,
            'closesAt' => '23:59',
            'opensAt' => null,
            'days' => array_map(fn (int $day) => [
                'dayOfWeek' => $day,
                'name' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'][$day - 1],
                'shifts' => [['start' => '00:00', 'end' => '23:59', 'crossesMidnight' => false]],
            ], range(1, 7)),
        ];
    }

    protected function product(string $id, string $slug, string $name, string $categoryId, array $overrides = []): array
    {
        return array_replace([
            'id' => $id,
            'slug' => $slug,
            'name' => $name,
            'description' => "Descripcion de {$name}",
            'category' => ['id' => $categoryId, 'name' => $categoryId === '1' ? 'Tacos' : 'Bebidas', 'description' => ''],
            'basePrice' => 100,
            'image' => ['url' => "https://erp.test/storage/{$slug}.jpg", 'alt' => $name],
            'available' => true,
            'unavailableReason' => null,
            'promotion' => null,
            'featured' => null,
        ], $overrides);
    }

    protected function products(): array
    {
        return [
            $this->product('1', 'tacos-al-pastor', 'Tacos al pastor', '1', [
                'featured' => ['isFeatured' => true, 'position' => 2],
                'promotion' => [
                    'id' => '1', 'name' => 'Promo 20', 'type' => 'discount', 'source' => 'own', 'label' => '20%',
                    'discount' => [
                        'kind' => 'percentage', 'value' => 20, 'variantId' => '9', 'originalPrice' => 129,
                        'finalPrice' => 103.2, 'savings' => 25.8, 'variantsAffected' => 3, 'variantsTotal' => 3,
                    ],
                    'buyGet' => null,
                ],
            ]),
            $this->product('2', 'agua-de-horchata', 'Agua de horchata', '2', [
                'basePrice' => 35,
                'featured' => ['isFeatured' => true, 'position' => 1],
            ]),
            $this->product('3', 'gringas', 'Gringas', '1', ['image' => ['url' => '/images/placeholder-dish.svg', 'alt' => '']]),
        ];
    }

    /** A product page with variants (one discounted) and the three customization controls. */
    protected function productDetail(array $overrides = []): array
    {
        return array_replace([
            'id' => '1',
            'slug' => 'tacos-al-pastor',
            'name' => 'Tacos al pastor',
            'description' => 'Con piña y cilantro',
            'category' => 'Tacos',
            'image' => ['url' => 'https://erp.test/storage/tacos.jpg', 'alt' => 'Tacos al pastor'],
            'available' => true,
            'unavailableReason' => null,
            'basePrice' => 90,
            'hasVariants' => true,
            'defaultVariantId' => '11',
            'variants' => [
                ['id' => '10', 'name' => 'Orden chica', 'price' => 90, 'finalPrice' => null, 'savings' => null, 'image' => null],
                ['id' => '11', 'name' => 'Orden grande', 'price' => 150, 'finalPrice' => 120, 'savings' => 30, 'image' => ['url' => 'https://erp.test/storage/grande.jpg', 'alt' => 'Grande']],
                ['id' => '12', 'name' => '', 'price' => 200, 'finalPrice' => null, 'savings' => null, 'image' => null],
            ],
            'customizations' => [
                ['id' => '5', 'name' => 'Salsa', 'control' => 'radio', 'required' => true, 'min' => 1, 'max' => 1, 'maxPerOption' => null, 'defaultOptionId' => '51', 'options' => [
                    ['id' => '50', 'label' => 'Verde', 'price' => 0],
                    ['id' => '51', 'label' => 'Roja', 'price' => 5],
                ]],
                ['id' => '6', 'name' => 'Extras', 'control' => 'quantity', 'required' => false, 'min' => 0, 'max' => 4, 'maxPerOption' => 2, 'defaultOptionId' => null, 'options' => [
                    ['id' => '60', 'label' => 'Queso', 'price' => 15],
                ]],
                ['id' => '7', 'name' => 'Cubiertos', 'control' => 'checkbox', 'required' => false, 'min' => 0, 'max' => 0, 'maxPerOption' => null, 'defaultOptionId' => null, 'options' => [
                    ['id' => '70', 'label' => 'Tenedor', 'price' => 0],
                ]],
            ],
            'promotion' => [
                'id' => '1', 'name' => 'Promo 20', 'type' => 'discount', 'source' => 'own', 'label' => '20%',
                'discount' => ['kind' => 'percentage', 'value' => 20, 'variantId' => '11', 'originalPrice' => 150, 'finalPrice' => 120, 'savings' => 30, 'variantsAffected' => 1, 'variantsTotal' => 3],
                'buyGet' => null,
            ],
        ], $overrides);
    }

    /**
     * Fakes the three reads of a screen. Any of them may be replaced by a response or a
     * failure (Http::response(...), Http::failedConnection()).
     */
    protected function fakeShopApi(mixed $store = null, mixed $schedule = null, mixed $products = null, array $more = []): void
    {
        Http::fake([
            ...$more,
            'erp.test/api/store/schedule' => $schedule ?? Http::response(['data' => $this->openSchedule()]),
            'erp.test/api/store' => $store ?? Http::response(['data' => $this->store()]),
            'erp.test/api/products' => $products ?? Http::response(['data' => $this->products()]),
        ]);
    }
}
