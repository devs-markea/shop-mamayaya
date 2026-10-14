<?php

namespace App\Http\Controllers;

use App\Services\Cart\CartService;
use App\Services\Cart\CartSession;
use App\Services\Orders\OrderService;
use App\Services\Store\StoreContext;
use App\Support\Checkout;
use App\Support\CheckoutDraft;
use App\Support\Confirmation;
use App\Support\ErrorCodes;
use App\Support\Js;
use App\Support\Price;
use App\Support\Schedule;
use App\Support\WhatsApp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The acknowledgement of the three methods, `/mamayaya/recibido`. Port of
 * src/pages/mamayaya/recibido.astro.
 *
 *   transfer      the "Pedido recibido" seal, the steps and the account to transfer to
 *   cash          the same seal and its steps: the money is handed over in person
 *   mercado_pago  the charge's state (approved, waiting for the webhook, rejected) with
 *                 the order summary and the action that fits (App\Support\Confirmation)
 *
 * Mercado Pago sends the buyer back here (`?order={id}`); /mamayaya/confirmado redirects
 * here for charges created before the change. The order is reread on every visit.
 */
class ConfirmationController extends Controller
{
    /** The mockup's folio, so the design can be reviewed in test mode without an order. */
    private const MOCK_FOLIO = '91';

    public function __construct(
        private readonly StoreContext $context,
        private readonly CartService $cart,
        private readonly CartSession $session,
        private readonly OrderService $orders,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $testMode = (bool) config('shop.test_mode');
        $token = $this->session->resolve($request);

        // The order isn't kept in the cookie, only pointed to: its status changes without
        // the buyer touching anything.
        $pointer = CheckoutDraft::parseOrderPointer($request->cookie(config('shop.cookies.order')));

        // Mercado Pago's return carries `?order={id}`, a fallback if the cookie got lost
        // (the API scopes orders to the session and answers 404 for someone else's). NOTHING
        // ELSE from the URL is used: the payment's state is read by rereading the order.
        $hintedId = $request->query('order');
        $hintedId = is_string($hintedId) && $hintedId !== '' ? $hintedId : null;
        $orderId = isset($pointer['id']) ? (string) $pointer['id'] : $hintedId;

        // WHO CAN BE HERE: only the pointed order proves a closing happened. Without a
        // pointer and without the gateway's id there's nothing to pull from, so back to the
        // menu before asking the API anything (302: the rule depends on the session). Test
        // mode serves the screen as is, to review the design without a purchase.
        if ($orderId === null && ! $testMode) {
            return redirect('/mamayaya');
        }

        $context = $this->context->forRequest($request, withCart: false);
        ['store' => $store] = $context;

        // `failed` next to the order, because not reading it means two things: null is a
        // 404 (an id that isn't valid, out of the screen); a throw is the house failing, and
        // nobody gets thrown out for that: the screen is served with what loaded, and the
        // log carries the code.
        $order = null;
        $failed = false;

        if ($orderId !== null) {
            try {
                $order = $this->orders->find($orderId, $token);
            } catch (Throwable $e) {
                $failed = true;
                Log::error('[recibido] '.ErrorCodes::fromException(ErrorCodes::RECEIPT, $e).' fallo GET /api/orders', ['error' => $e->getMessage()]);
            }
        }

        // The cart: the desktop chip's amount, and what Mercado Pago's summary lists when
        // the screen is reviewed without an order.
        $cart = null;

        try {
            $cart = $this->cart->get($token);
        } catch (Throwable $e) {
            Log::error('[recibido] fallo GET /api/cart', ['error' => $e->getMessage()]);
        }

        // There was an id and the API says it's nobody's (a hand-made `?order=` included).
        if ($orderId !== null && $order === null && ! $failed && ! $testMode) {
            return redirect('/mamayaya');
        }

        // HOW IT'S PAID AND HOW IT'S RECEIVED: the order's rule, the pointer is the
        // fallback when the read fails. Without either, `?order=` only comes from the
        // gateway, so it's Mercado Pago; without that, the transfer (test mode).
        $method = $order['paymentMethod'] ?? $pointer['method']
            ?? ($order !== null ? Confirmation::inferMethod($order) : ($hintedId !== null ? 'mercado_pago' : null));

        $deliveryType = $order['deliveryType'] ?? $pointer['deliveryType'] ?? 'pickup';

        // MERCADO PAGO: the charge's state. The other two don't go through here: their
        // payment happens outside the store and their acknowledgement is always the same.
        $view = $method === 'mercado_pago'
            ? ($order !== null ? Confirmation::resolve($method, $order, $pointer['chargeStarted'] ?? null) : Confirmation::withoutOrder())
            : null;

        $actionKind = $view['action']['kind'] ?? null;

        // Charging again is the only action that wraps the screen in a form and the only
        // one that needs the device fingerprint.
        $retry = $actionKind === 'pay';

        // THE ACKNOWLEDGEMENT IS SEEN: noted in the pointer so the payment screens know.
        // Only a really read order, only with a cookie to note it in, and only when nothing
        // is left pending in the store (with a charge to retry or a webhook to wait for,
        // this screen is STILL their place).
        if ($order !== null && $pointer !== null && empty($pointer['seen']) && ($view === null || $actionKind === 'whatsapp')) {
            Cookie::queue(CheckoutDraft::seenPointerCookie($pointer, app()->isProduction()));
        }

        // The store's chat, with the order already identified. The template is chosen by
        // the two pieces of data (delivery and method), not by this screen.
        $phone = (string) ($store['whatsapp']['phone'] ?? '');
        $templates = $store['whatsapp']['templates'] ?? [];
        $template = $method !== null ? WhatsApp::pickTemplate($templates, $deliveryType, $method) : null;

        $values = $order !== null ? WhatsApp::orderMessageValues($order, [
            'items' => $store['whatsapp']['items'] ?? [],
            'location' => $store['location'] ?? [],
            'publicUrl' => $store['publicUrl'] ?? null,
        ]) : null;

        // Only with someone to write to, something to talk about and something to say:
        // otherwise the button stays inert instead of opening an empty chat.
        $whatsapp = $phone !== '' && $values !== null && $template ? WhatsApp::url($phone, WhatsApp::fillMessage($template, $values)) : null;

        // The order status message: ONE for every method and mode, read directly. It's
        // the template of ANOTHER button, so neither covers for the other.
        $statusTemplate = $templates['orderStatus'] ?? null;
        $statusWhatsapp = $phone !== '' && $values !== null && $statusTemplate ? WhatsApp::url($phone, WhatsApp::fillMessage($statusTemplate, $values)) : null;

        // --- Mercado Pago: the order summary. With an order everything comes from it
        // (the checkout emptied the cart); its total already carries the shipping.
        $items = $order['items'] ?? [];
        $total = $order['total'] ?? 0;

        // Without an order the cart is painted, so the screen stays reviewable (test mode
        // and a failed read).
        if ($view !== null && $order === null && $cart !== null) {
            $items = $cart['items'] ?? [];
            $total = ($cart['total'] ?? 0) + CheckoutDraft::parse($request->cookie(config('shop.cookies.checkout')))['tip'];
        }

        // The folio is the `folio` (the counter the business sees), never the `id`.
        $folio = $order !== null ? Checkout::orderFolio($order) : self::MOCK_FOLIO;

        // "Actualizar" reloads this screen, keeping the URL's `order` in case the cookie
        // isn't there.
        $selfUrl = $hintedId !== null ? $request->getPathInfo().'?order='.Js::encodeURIComponent($hintedId) : $request->getPathInfo();

        $copy = $this->copy($method, $deliveryType, $order);

        // The seal. With Mercado Pago it says how the charge ended, and the folio only when
        // the view allows it (next to a failed payment it would look like a receipt).
        $status = $view !== null
            ? ['state' => $view['state'], 'folio' => $view['showFolio'] ? $folio : null, 'title' => $view['title'], 'note' => $view['note']]
            : ['state' => 'pending', 'folio' => null, 'title' => 'Pedido recibido', 'note' => $copy['note']];

        // The action, in one place: two controls paint it (the mobile bar and the desktop
        // column), and offering different things per width would be lying in one of them.
        $action = match ($actionKind) {
            'pay' => ['label' => $view['action']['label']],
            'refresh' => ['label' => 'Actualizar', 'href' => $selfUrl],
            default => ['label' => 'Abrir WhatsApp', 'icon' => 'whatsapp', 'variant' => 'whatsapp', 'href' => $whatsapp],
        };

        // THE SECOND ACTION, "Ver estado de pedido": the WhatsApp button cloned, with the
        // status message. Without a template or a phone it isn't painted.
        $statusAction = ($action['variant'] ?? null) === 'whatsapp' && $phone !== '' && $statusTemplate
            ? [...$action, 'label' => 'Ver estado de pedido', 'href' => $statusWhatsapp]
            : null;

        return view('pages.confirmation', [
            ...$context,
            'cart' => $cart,
            'shift' => $context['schedule'] ? Schedule::shiftLabel($context['schedule']) : null,
            'deliveryType' => $deliveryType,
            'order' => $order,
            'view' => $view,
            'retry' => $retry,
            'awaiting' => $actionKind === 'refresh',
            'cash' => $method === 'efectivo',
            'status' => $status,
            'items' => $items,
            'total' => $total,
            'steps' => $copy['steps'],
            'description' => $view !== null ? $view['note'] : $copy['description'],
            'action' => $action,
            'statusAction' => $statusAction,
            'actionNote' => $view !== null ? ($view['actionNote'] ?? null) : $copy['barNote'],
        ]);
    }

    /**
     * The copy of the three closings without a gateway, together: it reads in one go how
     * they differ. All three carry steps, because something is left to do and the order is
     * part of the data. In cash the delivery mode splits the seal's and the bar's notice.
     *
     * @return array{description: string, note: string, steps: list<string>, barNote: string}
     */
    private function copy(?string $method, string $deliveryType, ?array $order): array
    {
        if ($method !== 'efectivo') {
            return [
                'description' => 'Envia tu comprobante de transferencia para confirmar el pedido.',
                'note' => 'Envia tu comprobante por WhatsApp para que podamos confirmar tu pedido.',
                'steps' => [
                    'Realiza tu transferencia bancaria',
                    'Envia el comprobante por WhatsApp',
                    'Recibe confirmacion de tu pedido',
                ],
                'barNote' => 'Tu pedido quedara pendiente hasta confirmar el pago',
            ];
        }

        // The second step carries the amount (the reread order's total, shipping included),
        // and stays without a figure rather than inventing one when there's no order.
        $steps = [
            'Espera la confirmacion de tu pedido por WhatsApp',
            $order !== null ? 'Prepara el monto exacto: '.Price::format($order['total'] ?? 0) : 'Prepara el monto exacto de tu pedido',
            'Paga al repartidor al momento de la entrega o en el local para pick-up',
        ];

        return $deliveryType !== 'delivery'
            ? [
                'description' => 'Recoge tu pedido en el local y pagalo en efectivo.',
                'note' => 'Te avisaremos por WhatsApp cuando tu pedido este listo para recoger.',
                'steps' => $steps,
                'barNote' => 'Pagaras en efectivo al recoger tu pedido',
            ]
            : [
                'description' => 'Paga en efectivo al recibir tu pedido.',
                'note' => 'Tu pedido ha sido recibido. Prepara el monto exacto para el momento de la entrega.',
                'steps' => $steps,
                'barNote' => 'Recibiras una confirmacion por WhatsApp cuando tu pedido este en camino',
            ];
    }
}
