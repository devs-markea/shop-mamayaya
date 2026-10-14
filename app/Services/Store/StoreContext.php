<?php

namespace App\Services\Store;

use App\Services\Cart\CartService;
use App\Services\Cart\CartSession;
use App\Support\CheckoutDraft;
use App\Support\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What every storefront screen reads besides its own content: the store settings, the
 * schedule, the session's cart (for the desktop bar's chip) and the checkout draft. Plus
 * the two conditions that decide whether the location and schedule sheets exist,
 * computed in ONE place: the page that mounts the sheets and the bars that open them
 * must agree, or a button could open a sheet that isn't there.
 *
 * Never throws: settings fall back, the schedule is null and the cart is null.
 */
class StoreContext
{
    public function __construct(
        private readonly StoreService $store,
        private readonly CartService $cart,
        private readonly CartSession $session,
    ) {}

    /**
     * @param  bool  $withCart  False for the screens that read the cart themselves (the
     *                          cart page, which must tell a failure from an empty cart).
     * @return array{store: array, schedule: ?array, cart: ?array, draft: array, deliveryType: string, hasLocation: bool, hasSchedule: bool, closed: bool}
     */
    public function forRequest(Request $request, bool $withCart = true): array
    {
        $store = $this->store->settings();
        $schedule = $this->store->schedule();
        $draft = CheckoutDraft::parse($request->cookie(config('shop.cookies.checkout')));

        return [
            'store' => $store,
            'schedule' => $schedule,
            'cart' => $withCart ? $this->cart($request) : null,
            'draft' => $draft,
            // The mode chosen on the home page or on /mamayaya/datos.
            'deliveryType' => $draft['deliveryType'],
            // Address or link: either one is worth opening. The short address and the
            // message don't count.
            'hasLocation' => $store['location']['address'] !== '' || $store['location']['mapsUrl'] !== '',
            // Without shifts the API answers `isOpen: null` and empty days: nothing to show.
            'hasSchedule' => $schedule !== null && $schedule['days'] !== [],
            'closed' => Schedule::isClosed($schedule),
        ];
    }

    /**
     * The session's cart, or null. Only read when the session already has a token:
     * GET /api/cart creates one, and opening a cart per visit to read a zero that was
     * already known helps nobody.
     */
    private function cart(Request $request): ?array
    {
        $token = $this->session->peek($request);

        if (! $token) {
            return null;
        }

        try {
            return $this->cart->get($token);
        } catch (Throwable $e) {
            Log::error('[tienda] fallo GET /api/cart', ['path' => $request->path(), 'error' => $e->getMessage()]);

            return null;
        }
    }
}
