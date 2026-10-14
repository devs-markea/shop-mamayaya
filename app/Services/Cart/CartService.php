<?php

namespace App\Services\Cart;

use App\Services\ShopApi\ShopApiClient;
use App\Services\ShopApi\ShopApiException;
use App\Support\CheckoutDraft;
use App\Support\Price;
use App\Support\Shipping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The cart, read from the server side. Port of src/lib/cart.ts.
 *
 * No route takes a cart id: the session travels in `X-Cart-Token`, taken from this
 * store's httpOnly cookie (see CartSession). Every operation returns the WHOLE cart
 * recomputed, so the interface never adds anything up on its own.
 */
class CartService
{
    public function __construct(private readonly ShopApiClient $api) {}

    /**
     * The session's cart. The API creates it empty on the first visit, which is why the
     * screens that only SHOW it ask with CartSession::peek() and skip this without a token.
     *
     * @throws ShopApiException
     */
    public function get(string $token): array
    {
        return (array) $this->api->unwrap(
            $this->api->fetch('GET', '/api/cart', ['X-Cart-Token' => $token]),
            '/api/cart',
        );
    }

    /**
     * The session's last shipping quote, or null. NEVER THROWS and makes no noise: a 404
     * (no quote, or the endpoint not published) means the same as "nothing quoted", and
     * the store paints "Por cotizar", a state it already knows. Port of
     * src/lib/shipping-quote.ts.
     */
    public function shippingQuote(string $token): ?array
    {
        try {
            $response = $this->api->fetch('GET', '/api/shipping/quote', ['X-Cart-Token' => $token]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json('data');

            return Shipping::quoteFromResponse(is_array($data) ? $data : null);
        } catch (Throwable $e) {
            Log::error('[envio] fallo GET /api/shipping/quote', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * The shipping this draft can show, recovered when needed: without a shared location
     * there is none (it's quoted against a point), and with one but no amount at hand it's
     * asked to the API (the draft cookie lasts two hours, the cart session thirty days).
     * Pickup doesn't ask: there's no shipping to talk about.
     */
    public function quoteForDraft(array $draft, string $token): ?array
    {
        if ($draft['deliveryType'] === 'pickup' || ! CheckoutDraft::hasSharedLocation($draft)) {
            return null;
        }

        return $draft['shipping'] ?? $this->shippingQuote($token);
    }

    /** The accessible name of the desktop bar's cart chip. Port of cartChipLabel(). */
    public static function chipLabel(float|int $total, int $count): string
    {
        if ($count <= 0) {
            return 'Mi pedido, vacio';
        }

        return "Mi pedido: {$count} ".($count === 1 ? 'articulo' : 'articulos').', '.Price::format($total);
    }
}
