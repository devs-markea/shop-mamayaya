<?php

namespace Tests\Concerns;

/**
 * Synthetic GET /api/cart responses covering every branch of the cart's view: plain
 * lines, discounts, incomplete "buy X get Y" hints, rows split between a line and a
 * group, groups with free units, dishes without photo or without page, and names that
 * need escaping.
 */
trait FakesCarts
{
    protected function cartLine(array $overrides = []): array
    {
        return array_replace([
            'kind' => 'item',
            'itemId' => 'a1',
            'productId' => '1',
            'productSlug' => 'tacos-al-pastor',
            'name' => 'Tacos al pastor',
            'image' => ['url' => 'https://erp.test/storage/tacos.jpg', 'alt' => 'Tacos'],
            'variantId' => '10',
            'variantName' => 'Orden grande',
            'options' => [
                ['optionId' => '51', 'label' => 'Roja', 'group' => 'Salsa', 'price' => 5, 'quantity' => 1],
                ['optionId' => '60', 'label' => 'Queso', 'group' => 'Extras', 'price' => 15, 'quantity' => 2],
            ],
            'quantity' => 2,
            'unitPrice' => 125,
            'originalTotal' => 250,
            'discount' => 0,
            'total' => 250,
            'promotion' => null,
            'promoHint' => null,
        ], $overrides);
    }

    /** A full row of the cart's `items` (what the closing screens' order detail lists). */
    protected function cartItem(array $overrides = []): array
    {
        return array_replace([
            'id' => 'a1',
            'productId' => '1',
            'productSlug' => 'tacos-al-pastor',
            'name' => 'Tacos al pastor',
            'image' => ['url' => 'https://erp.test/storage/tacos.jpg', 'alt' => 'Tacos'],
            'variantId' => '10',
            'variantName' => 'Orden grande',
            'quantity' => 2,
            'basePrice' => 90,
            'optionsTotal' => 35,
            'unitPrice' => 125,
            'originalUnitPrice' => 125,
            'originalLineTotal' => 250,
            'options' => [
                ['optionId' => '51', 'label' => 'Roja', 'group' => 'Salsa', 'price' => 5, 'quantity' => 1],
                ['optionId' => '60', 'label' => 'Queso', 'group' => 'Extras', 'price' => 15, 'quantity' => 2],
            ],
            'promotion' => null,
            'discount' => 0,
            'freeQuantity' => 0,
            'lineTotal' => 250,
        ], $overrides);
    }

    protected function cart(array $overrides = []): array
    {
        return array_replace([
            'id' => 'cart-1',
            'currency' => 'MXN',
            'itemsCount' => 7,
            'items' => [
                ['id' => 'a1', 'quantity' => 2],
                ['id' => 'b2', 'quantity' => 3],
                ['id' => 'c3', 'quantity' => 1],
                ['id' => 'd4', 'quantity' => 1],
            ],
            'lines' => [
                $this->cartLine(),
                // A discounted line whose row also gives units to the group below.
                $this->cartLine([
                    'itemId' => 'b2', 'productId' => '2', 'productSlug' => 'poke milanesa/especial', 'name' => 'Poke "Milanesa" & <Co>',
                    'image' => ['url' => '/storage/poke.jpg', 'alt' => "Poke d'Or"], 'variantName' => null, 'options' => [],
                    'quantity' => 1, 'unitPrice' => 100, 'originalTotal' => 100, 'discount' => 20, 'total' => 80,
                    'promotion' => ['id' => '1', 'name' => 'Promo 20', 'type' => 'percentage', 'source' => 'own', 'label' => '20%'],
                    'promoHint' => ['type' => 'buy_get', 'label' => '2x1', 'missing' => 1],
                ]),
                // A dish that left the catalog (no page) and has no photo.
                $this->cartLine([
                    'itemId' => 'c3', 'productSlug' => null, 'name' => 'Agua del día',
                    'image' => ['url' => '/images/placeholder-dish.svg', 'alt' => ''], 'variantName' => '', 'options' => [],
                    'quantity' => 1, 'unitPrice' => 35, 'originalTotal' => 35, 'total' => 35,
                ]),
                [
                    'kind' => 'promoGroup',
                    'productId' => '2',
                    'productSlug' => 'poke-milanesa',
                    'name' => 'Poke Milanesa',
                    'image' => ['url' => 'https://erp.test/storage/poke.jpg', 'alt' => 'Poke'],
                    'promotion' => ['id' => '2', 'name' => '2x1', 'type' => 'buy_get', 'source' => 'own', 'label' => '2x1'],
                    'units' => [
                        ['itemId' => 'd4', 'variantId' => '20', 'variantName' => 'Doble', 'options' => [['optionId' => '9', 'label' => 'Extra', 'group' => 'X', 'price' => 10, 'quantity' => 3]], 'unitPrice' => 110, 'isFree' => false],
                        ['itemId' => 'b2', 'variantId' => null, 'variantName' => null, 'options' => [], 'unitPrice' => 100, 'isFree' => true],
                        ['itemId' => 'b2', 'variantId' => null, 'variantName' => null, 'options' => [], 'unitPrice' => 100, 'isFree' => false],
                    ],
                    'originalTotal' => 310,
                    'discount' => 100,
                    'total' => 210,
                ],
            ],
            'subtotal' => 695,
            'discountTotal' => 120,
            'total' => 575,
            'shippingTotal' => 0,
            'tipTotal' => 0,
        ], $overrides);
    }
}
