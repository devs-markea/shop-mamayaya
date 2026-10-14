<?php

namespace App\Http\Controllers;

use App\Services\Cart\CartService;
use App\Services\Cart\CartSession;
use App\Services\Store\StoreContext;
use App\Support\CartView;
use App\Support\ErrorCodes;
use App\Support\Schedule;
use App\Support\Shipping;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The current order, `/mamayaya/carrito`. Port of src/pages/mamayaya/carrito.astro.
 *
 * One markup for both screens: on mobile the 48px bar with the total, the column with
 * the lines and the comments, and "Continuar" in the fixed bottom bar; on desktop the
 * store bar, the order on the left and the summary card on the right, which closes the
 * account and continues the flow.
 *
 * The lines are painted by App\Support\CartView, the PHP twin of the renderLines() the
 * browser uses to repaint them after every change (resources/js/scripts/order-summary.ts).
 */
class CartController extends Controller
{
    public function __construct(
        private readonly StoreContext $context,
        private readonly CartService $cart,
        private readonly CartSession $session,
    ) {}

    public function show(Request $request): View
    {
        // The cart reads its own order: here a failure must be told apart from an empty
        // cart, and the session is opened if this is the first visit.
        $context = $this->context->forRequest($request, withCart: false);
        ['store' => $store, 'draft' => $draft] = $context;

        $token = $this->session->resolve($request);

        $cart = null;
        $cartCode = null;

        try {
            $cart = $this->cart->get($token);
        } catch (Throwable $e) {
            $cartCode = ErrorCodes::fromException(ErrorCodes::CART, $e);

            Log::error("[pedido] {$cartCode} fallo GET /api/cart", ['error' => $e->getMessage()]);
        }

        $assetBase = rtrim((string) config('services.shop_api.url'), '/');
        $total = $cart['total'] ?? 0;
        $lines = $cart['lines'] ?? [];

        // Free shipping is the business's: `threshold` paints the bar, `always` has nothing
        // to advance, `none` promises nothing. The threshold and the mode travel in data-*
        // so the script can resolve shipping again with each new total.
        $freeShipping = $store['delivery']['freeShipping'] ?? null;
        $threshold = ($freeShipping['mode'] ?? null) === 'threshold' ? ($freeShipping['threshold'] ?? 0) : 0;

        // The shipping of this draft: the draft's, or the one the API keeps for this
        // session if the cookie lost it. Without a shared location there's none, which is
        // the normal case here (the location is shared on /mamayaya/datos).
        $quote = $this->cart->quoteForDraft($draft, $token);

        return view('pages.cart', [
            ...$context,
            'cart' => $cart,
            'unreachable' => $cart === null,
            'cartCode' => $cartCode,
            'total' => $total,
            'subtotal' => $cart['subtotal'] ?? 0,
            'discountTotal' => $cart['discountTotal'] ?? 0,
            'lines' => $lines,
            'assetBase' => $assetBase,
            'linesHtml' => $cart ? CartView::renderLines($cart, $assetBase) : '',
            'freeShipping' => $freeShipping,
            'threshold' => $threshold,
            // With an empty cart there's no progress to show: a bar at zero in front of
            // nothing can only say false things.
            'showProgress' => $threshold > 0 && $lines !== [],
            'remaining' => Shipping::remainingForFreeShipping($total, $threshold),
            'progress' => Shipping::progress($total, $threshold),
            'shipping' => Shipping::resolve($draft['deliveryType'] === 'pickup', $quote, $total, $freeShipping),
            'shift' => $context['schedule'] ? Schedule::shiftLabel($context['schedule']) : null,
        ]);
    }
}
