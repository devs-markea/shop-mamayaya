<?php

namespace App\Http\Controllers;

use App\Services\Cart\CartService;
use App\Services\Cart\CartSession;
use App\Services\Store\StoreContext;
use App\Support\CheckoutDraft;
use App\Support\Schedule;
use App\Support\Shipping;
use App\Support\Tips;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The three payment screens. Ports of src/pages/mamayaya/pago/{index,transferencia,efectivo}.astro.
 *
 *   /mamayaya/pago                  Tip, method and total. Continuing goes to the
 *                                   method's screen, or closes the order (Mercado Pago):
 *                                   resources/js/scripts/payment-summary.ts.
 *   /mamayaya/pago/transferencia    The destination account and the order detail.
 *   /mamayaya/pago/efectivo         How it's paid and the order detail.
 *
 * The last two close the order with resources/js/scripts/confirm-order.ts: the order
 * doesn't exist yet (it's created on confirming), so what they list is the cart.
 *
 * Every amount is resolved here and not in the browser: the shipping and the tip have to
 * be in the first paint, next to the products, and not show up later.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly StoreContext $context,
        private readonly CartService $cart,
        private readonly CartSession $session,
    ) {}

    public function summary(Request $request): View|RedirectResponse
    {
        // Mercado Pago sends the buyer back here when the charge is rejected or
        // cancelled. The order EXISTS and the cart was emptied on closing, so charging
        // again lives in the acknowledgement. The parameter is only a hint of where to go.
        $rejectedOrder = $request->query('order');

        if ($request->query('pago') === 'rechazado' && is_string($rejectedOrder) && $rejectedOrder !== '') {
            return redirect('/mamayaya/recibido?order='.rawurlencode($rejectedOrder));
        }

        $token = $this->session->resolve($request);
        $cart = $this->readCart($token, '[resumen]');

        // An empty cart WITH the order pointer means a purchase was closed here: to the
        // acknowledgement while it hasn't been seen, to the menu once it has (repeating
        // "Pedido confirmado" on every back reads as paying twice). Without the pointer
        // there's simply nothing to pay: back to the order, which explains it.
        $pointer = CheckoutDraft::parseOrderPointer($request->cookie(config('shop.cookies.order')));

        if ($pointer !== null && self::isEmptyCart($cart)) {
            return redirect(($pointer['seen'] ?? false) ? '/mamayaya' : '/mamayaya/recibido');
        }

        if (self::isEmptyCart($cart)) {
            return redirect('/mamayaya/carrito');
        }

        $raw = $request->cookie(config('shop.cookies.checkout'));
        $context = $this->context->forRequest($request, withCart: false);
        ['store' => $store, 'draft' => $draft] = $context;

        // Pickup only has cash, so the previous choice isn't restored, and there's no
        // shipping to quote.
        $pickup = $draft['deliveryType'] === 'pickup';
        $products = $cart['total'] ?? 0;

        // The business decides the tip: switched off it isn't asked for, and without
        // amounts there's no selector to paint.
        $tipAmounts = Tips::normalizeAmounts(($store['tips']['enabled'] ?? false) ? ($store['tips']['amounts'] ?? []) : []);
        $showTips = $tipAmounts !== [];

        // Without a draft the first amount rules, which after normalizing is the 0: the
        // tip is offered, never applied on its own. "No draft" isn't "chose $0".
        $tip = $showTips ? (($raw !== null && $raw !== '') ? $draft['tip'] : $tipAmounts[0]) : 0;

        return view('pages.payment', [
            ...$this->screen($context, $cart),
            'products' => $products,
            'tip' => $tip,
            'tipAmounts' => $tipAmounts,
            'showTips' => $showTips,
            'method' => $pickup ? 'efectivo' : $draft['paymentMethod'],
            // The transfer is only offered when the business published its account.
            'hasBankTransfer' => ($store['bankTransfer'] ?? null) !== null,
            'shipping' => $this->shipping($draft, $token, $products, $store),
        ]);
    }

    public function transfer(Request $request): View|RedirectResponse
    {
        // Pickup is paid at the store: transferring is paying in advance for an order
        // that goes out on the street. Checked before asking for anything: it's local
        // and free, so whoever shouldn't be here leaves without costing the API a call.
        $draft = CheckoutDraft::parse($request->cookie(config('shop.cookies.checkout')));

        if ($draft['deliveryType'] === 'pickup') {
            return redirect('/mamayaya/pago');
        }

        $token = $this->session->resolve($request);
        $context = $this->context->forRequest($request, withCart: false);
        $store = $context['store'];

        // If the cart doesn't answer the screen is served anyway: the bank account is
        // what one comes here to read.
        $cart = $this->readCart($token, '[transferencia]');

        // Without an order there's nothing to transfer: an account inviting to pay for
        // nothing.
        if (self::isEmptyCart($cart)) {
            return redirect('/mamayaya/carrito');
        }

        // Without a published account there's no transfer to make.
        $bankTransfer = $store['bankTransfer'] ?? null;

        if ($bankTransfer === null) {
            return redirect('/mamayaya/pago');
        }

        $products = $cart['total'] ?? 0;
        $shipping = $this->shipping($draft, $token, $products, $store);

        return view('pages.payment-transfer', [
            ...$this->screen($context, $cart),
            ...$this->detail($cart, $products, $draft['tip'], $shipping),
            'bankTransfer' => $bankTransfer,
            // The notice only warns about what's really missing: with the shipping
            // measured, the amount above is final.
            'infoNote' => $shipping['state'] === 'pending'
                ? 'Al finalizar tu pedido se te indicara el costo de envio exacto. Por favor realiza la transferencia una vez confirmado.'
                : 'Realiza la transferencia por el importe total y envianos el comprobante para confirmar tu pedido.',
        ]);
    }

    public function cash(Request $request): View|RedirectResponse
    {
        $token = $this->session->resolve($request);
        $context = $this->context->forRequest($request, withCart: false);
        ['store' => $store, 'draft' => $draft] = $context;

        // If the cart doesn't answer the screen is served anyway: how it's paid is what
        // one comes here to read.
        $cart = $this->readCart($token, '[efectivo]');

        // TWO GUARDS, AND THE ORDER MATTERS. An empty cart isn't fixed on /mamayaya/datos
        // (filling in an address creates no lines), so it goes first.
        if (self::isEmptyCart($cart)) {
            return redirect('/mamayaya/carrito');
        }

        // Without name, phone or address "Confirmar pedido" could only fail (the draft
        // expires after two hours): back to the delivery data, where they're filled in.
        if (CheckoutDraft::draftGaps($draft) !== []) {
            return redirect('/mamayaya/datos');
        }

        $products = $cart['total'] ?? 0;
        $shipping = $this->shipping($draft, $token, $products, $store);

        return view('pages.payment-cash', [
            ...$this->screen($context, $cart),
            ...$this->detail($cart, $products, $draft['tip'], $shipping),
            // The shipping is only mentioned while something is left to say about it.
            'infoNote' => $shipping['state'] === 'pending'
                ? 'Se recomienda preparar el monto exacto para facilitar la entrega. El costo de envio exacto se confirmara al finalizar tu pedido.'
                : 'Se recomienda preparar el monto exacto para facilitar la entrega.',
        ]);
    }

    /** The session's cart, or null when the read fails (which does NOT count as empty). */
    private function readCart(string $token, string $tag): ?array
    {
        try {
            return $this->cart->get($token);
        } catch (Throwable $e) {
            Log::error("{$tag} fallo GET /api/cart", ['error' => $e->getMessage()]);

            return null;
        }
    }

    private static function isEmptyCart(?array $cart): bool
    {
        return $cart !== null && ($cart['lines'] ?? []) === [];
    }

    /**
     * The shipping of this draft, resolved against the two rules above it: pickup has
     * none, and the business's free shipping beats any amount. The quote is the draft's,
     * or the one the API keeps for the session if the cookie lost it.
     */
    private function shipping(array $draft, string $token, float|int $products, array $store): array
    {
        return Shipping::resolve(
            $draft['deliveryType'] === 'pickup',
            $this->cart->quoteForDraft($draft, $token),
            $products,
            $store['delivery']['freeShipping'] ?? null,
        );
    }

    /** What every payment screen paints around its content: the desktop bar and the sheets. */
    private function screen(array $context, ?array $cart): array
    {
        return [
            ...$context,
            'cart' => $cart,
            'shift' => $context['schedule'] ? Schedule::shiftLabel($context['schedule']) : null,
        ];
    }

    /** The order detail of the two closing screens, with the total that's paid. */
    private function detail(?array $cart, float|int $products, int $tip, array $shipping): array
    {
        return [
            'items' => $cart['items'] ?? [],
            'products' => $products,
            // Before discounting, and what promotions take off: with them the buyer can
            // check the subtraction instead of trusting the final amount.
            'subtotal' => $cart['subtotal'] ?? 0,
            'discount' => $cart['discountTotal'] ?? 0,
            'tip' => $tip,
            'shipping' => $shipping,
            'total' => $products + $tip + $shipping['cost'],
        ];
    }
}
