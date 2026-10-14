<?php

namespace Tests\Concerns;

/**
 * Synthetic GET /api/orders/{id} responses for the acknowledgement's tests.
 */
trait FakesOrders
{
    protected function storeOrder(array $overrides = []): array
    {
        return array_replace([
            'id' => 'ord-1',
            'folio' => 91,
            'folioLabel' => '#91',
            'orderNumber' => null,
            'status' => 'awaiting_verification',
            'statusLabel' => 'Por confirmar',
            'isActive' => true,
            'deliveryType' => 'delivery',
            'paymentMethod' => 'bank_transfer',
            'subtotal' => 695,
            'discountTotal' => 120,
            'shippingTotal' => 45,
            'tipTotal' => 10,
            'total' => 630,
            'shipping' => ['quoteId' => 'q1', 'distanceKm' => 3.2, 'zone' => 'ciudad', 'tier' => null],
            'notes' => null,
            'placedAt' => '2026-10-14T18:00:00Z',
            'customer' => ['name' => 'Ana', 'phone' => '+529981112233', 'street' => 'Coba', 'exteriorNumber' => '45', 'neighborhood' => 'Centro'],
            'items' => [
                ['quantity' => 2, 'name' => 'Tacos al pastor', 'variantName' => 'Orden grande', 'unitPrice' => 90, 'options' => [], 'lineTotal' => 250],
                ['quantity' => 1, 'name' => 'Agua', 'unitPrice' => 35, 'options' => []],
            ],
        ], $overrides);
    }

    /** The `sf_order` cookie as the browser writes it. */
    protected function orderPointer(array $pointer): string
    {
        return rawurlencode(json_encode($pointer));
    }
}
