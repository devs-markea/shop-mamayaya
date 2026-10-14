<?php

namespace App\Services\Orders;

use App\Services\ShopApi\ShopApiClient;
use App\Services\ShopApi\ShopApiException;
use App\Support\Js;

/**
 * The order read, `GET /api/orders/{id}`. Port of src/lib/orders.ts.
 *
 * The closing screens reread the order on every visit instead of keeping it in the
 * cookie, because its status changes without the buyer touching anything: a transfer is
 * born "Por confirmar" and moves on when the store accepts the payment, and Mercado
 * Pago's webhook moves the rest. Never cached.
 */
class OrderService
{
    public function __construct(private readonly ShopApiClient $api) {}

    /**
     * This session's order, or null when it doesn't exist or belongs to another session
     * (the API answers 404 to both, on purpose). Any other failure throws.
     *
     * @throws ShopApiException
     */
    public function find(string $id, string $token): ?array
    {
        $path = '/api/orders/'.Js::encodeURIComponent($id);

        try {
            return (array) $this->api->unwrap($this->api->fetch('GET', $path, ['X-Cart-Token' => $token]), $path);
        } catch (ShopApiException $e) {
            if ($e->status === 404) {
                return null;
            }

            throw $e;
        }
    }
}
