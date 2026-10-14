<?php

namespace App\Http\Controllers;

use App\Services\Cart\CartService;
use App\Services\Cart\CartSession;
use App\Services\Store\StoreContext;
use App\Support\Schedule;
use App\Support\Shipping;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivery data, `/mamayaya/datos`: contact, and where to deliver (delivery only). Port
 * of src/pages/mamayaya/datos.astro.
 *
 * It depends on the session: the delivery mode decides what this screen asks for, and
 * it's read from the draft, so coming back from payment doesn't reset it to delivery and
 * ask again for an address the order doesn't need. resources/js/scripts/delivery-form.ts
 * validates, saves the draft and moves on to /mamayaya/pago.
 *
 * On desktop it's built like the cart: the form on the left and the same summary card on
 * the right, whose button is a submit here (continuing starts by validating the fields).
 */
class DeliveryController extends Controller
{
    public function __construct(
        private readonly StoreContext $context,
        private readonly CartService $cart,
        private readonly CartSession $session,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $context = $this->context->forRequest($request, withCart: false);
        ['store' => $store, 'draft' => $draft] = $context;

        // The cart, for the summary card. If it doesn't answer, the card stays at zero but
        // the form is served anyway: delivery data can be filled in without the amount.
        $token = $this->session->resolve($request);
        $cart = null;

        try {
            $cart = $this->cart->get($token);
        } catch (Throwable $e) {
            Log::error('[datos] fallo GET /api/cart', ['error' => $e->getMessage()]);
        }

        // Without an order there's nowhere to deliver anything: back to the cart, where it
        // shows there are no lines. A failed read does NOT count as empty.
        if ($cart !== null && ($cart['lines'] ?? []) === []) {
            return redirect('/mamayaya/carrito');
        }

        // The shipping of this draft: without a shared location there is none, and that's
        // the point HERE: this is where it's shared, and until then it says "Por cotizar".
        $quote = $this->cart->quoteForDraft($draft, $token);

        $total = $cart['total'] ?? 0;
        $freeShipping = $store['delivery']['freeShipping'] ?? null;
        $testMode = (bool) config('shop.test_mode');

        return view('pages.delivery', [
            ...$context,
            'cart' => $cart,
            'total' => $total,
            'freeShipping' => $freeShipping,
            'threshold' => ($freeShipping['mode'] ?? null) === 'threshold' ? ($freeShipping['threshold'] ?? 0) : 0,
            // Resolved with what's known on entering; delivery-form.ts resolves it again
            // when the location is shared or the switch moves.
            'shipping' => Shipping::resolve($draft['deliveryType'] === 'pickup', $quote, $total, $freeShipping),
            // The delivery-area notice when the shared point fell outside the city. Written
            // by the backend when quoting; test mode silences it (here and in the script).
            'areaNotice' => $testMode ? '' : ($quote['notice'] ?? ''),
            'testMode' => $testMode,
            'mapsKey' => config('shop.google_maps_key') ?: null,
            'shift' => $context['schedule'] ? Schedule::shiftLabel($context['schedule']) : null,
        ]);
    }
}
