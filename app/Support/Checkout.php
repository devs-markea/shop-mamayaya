<?php

namespace App\Support;

/**
 * The server half of src/lib/checkout.ts: how an order is labelled on the closing
 * screens. The browser keeps its own copy (resources/js/lib/checkout.ts) for the order
 * it creates.
 *
 * Order shape (GET /api/orders/{id}): {id, folio?, folioLabel?, orderNumber, status,
 * statusLabel, isActive, deliveryType, paymentMethod?, subtotal, discountTotal,
 * shippingTotal, tipTotal, total, shipping?, notes?, placedAt, customer?, items, payment?}.
 */
final class Checkout
{
    /** How each method is labelled on the closing screens (the short name). */
    public const PAYMENT_LABEL = [
        'bank_transfer' => 'Transferencia',
        'efectivo' => 'Efectivo',
        'mercado_pago' => 'Mercado Pago',
    ];

    /**
     * The order number as it's labelled, without the "#": `49`. ONE place to read it, so
     * the acknowledgement and the WhatsApp message say the same number the business sees
     * in its panel. It's the `folio`, NOT the `id` (the access key): the two drift apart
     * as soon as an order is deleted. Without a folio (orders before 2026-08-11), `000`,
     * the same reserved value the backend sends in `folioLabel`.
     */
    public static function orderFolio(array $order): string
    {
        return isset($order['folio']) ? (string) $order['folio'] : '000';
    }
}
