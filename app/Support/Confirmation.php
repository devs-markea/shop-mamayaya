<?php

namespace App\Support;

/**
 * What the acknowledgement says, according to what really happened with the payment.
 * Port of src/lib/confirmation.ts.
 *
 * /mamayaya/recibido is where Mercado Pago sends the buyer back, and the order can be in
 * half a dozen situations there (paid, waiting for the webhook, rejected, never opened...).
 * Each one needs another title, notice and action, and saying it wrong costs both ways:
 * promising an order nobody paid, or asking again for money that was already paid.
 *
 * Two rules from the API contract: the return URL's parameters are NOT interpreted (the
 * webhook sets the truth, read by rereading the order), and the payment is deduced from
 * METHOD plus status, never from the status alone (`in_preparation` means "paid" in
 * Mercado Pago and is the INITIAL state in cash).
 *
 * View shape: {state: success|pending, title, note, showFolio, action: {kind:
 * whatsapp|refresh|pay, label?}, actionNote?}.
 */
final class Confirmation
{
    /** States in which the order is already charged and under way. */
    private const PAID = ['paid', 'in_preparation', 'ready', 'delivered'];

    /** Terminal states that aren't a failed charge but an order that's no longer alive. */
    private const CLOSED = ['cancelled', 'rejected', 'expired'];

    private const REFUNDED = ['refunded', 'partially_refunded'];

    /**
     * What's labelled when there's no order to read (test mode, or a failed read):
     * reviewable without stating anything about an order that doesn't exist.
     */
    public static function withoutOrder(): array
    {
        return [
            'state' => 'success',
            'title' => 'Pedido confirmado',
            'note' => 'Te avisaremos por WhatsApp cuando tu pedido este listo.',
            'showFolio' => true,
            'action' => ['kind' => 'whatsapp'],
        ];
    }

    /**
     * The method an order was closed with, when the cookie pointing to it is gone. A last
     * resort: only the `payment` block is firm (it only travels with Mercado Pago); between
     * the other two, pickup means cash and delivery is labelled as a transfer.
     */
    public static function inferMethod(?array $order): string
    {
        if (isset($order['payment'])) {
            return 'mercado_pago';
        }

        return ($order['deliveryType'] ?? null) === 'pickup' ? 'efectivo' : 'bank_transfer';
    }

    /**
     * The acknowledgement, resolved.
     *
     * @param  string  $method  The method it was closed with (the order pointer keeps it).
     * @param  array  $order  The order just reread from the API.
     * @param  bool|null  $chargeStarted  Whether the gateway got to open. Only known for
     *                                    Mercado Pago, and only by the store; null is "n/a".
     */
    public static function resolve(string $method, array $order, ?bool $chargeStarted = null): array
    {
        $status = $order['status'] ?? null;

        // --- What holds for the three methods ---

        if (in_array($status, self::REFUNDED, true)) {
            return [
                'state' => 'pending',
                'title' => $order['statusLabel'] ?? '',
                'note' => 'Tu pago se devolvio. Escribenos si necesitas ayuda con este pedido.',
                'showFolio' => true,
                'action' => ['kind' => 'whatsapp'],
            ];
        }

        if (in_array($status, self::CLOSED, true)) {
            return [
                'state' => 'pending',
                'title' => $order['statusLabel'] ?? '',
                'note' => 'Este pedido ya no esta en curso. Escribenos si crees que es un error.',
                'showFolio' => true,
                'action' => ['kind' => 'whatsapp'],
            ];
        }

        // --- Mercado Pago: the only method that charges through a gateway ---

        if ($method === 'mercado_pago') {
            if (in_array($status, self::PAID, true)) {
                return self::placed($method, $order);
            }

            $rejection = $order['payment']['rejection'] ?? null;

            // The charge died. The reason comes already translated and written for the
            // buyer, so it's shown as is: "no funds" and "check the security code" are
            // different ways out. The folio isn't labelled next to a failed payment.
            if (is_array($rejection)) {
                $retryable = ! empty($rejection['retryable']);

                return [
                    'state' => 'pending',
                    'title' => 'No pudimos cobrar tu pago',
                    'note' => $rejection['message'] ?? '',
                    'showFolio' => false,
                    'action' => $retryable ? ['kind' => 'pay', 'label' => 'Reintentar el pago'] : ['kind' => 'whatsapp'],
                    'actionNote' => $retryable
                        ? 'Tu pedido se conserva: solo se cobra de nuevo.'
                        : 'Tu pedido se conserva. Escribenos y lo resolvemos.',
                ];
            }

            // Without a rejection, "Pago pendiente" means one of two things and the API
            // doesn't tell them apart: whether the charge got to open, which only the
            // store knows.
            if ($chargeStarted === false) {
                return [
                    'state' => 'pending',
                    'title' => 'Tu pedido esta guardado',
                    'note' => 'No pudimos abrir el pago, asi que todavia no se ha cobrado nada.',
                    'showFolio' => false,
                    'action' => ['kind' => 'pay', 'label' => 'Pagar ahora'],
                    'actionNote' => 'Tu pedido se conserva mientras completas el pago.',
                ];
            }

            return [
                'state' => 'pending',
                'title' => 'Estamos confirmando tu pago',
                'note' => 'Mercado Pago nos lo confirma en unos segundos. Tu pedido ya esta guardado.',
                'showFolio' => true,
                'action' => ['kind' => 'refresh'],
                'actionNote' => 'Puedes actualizar en unos segundos para ver el estado.',
            ];
        }

        // --- Transfer and cash ---
        //
        // /mamayaya/recibido doesn't resolve them here today (it paints their steps);
        // kept in case a screen labels an order of any method with this function again.
        if ($method === 'bank_transfer' && $status === 'awaiting_verification') {
            return [
                'state' => 'pending',
                'title' => 'Pedido recibido',
                'note' => 'Estamos revisando tu comprobante. Te confirmamos por WhatsApp.',
                'showFolio' => true,
                'action' => ['kind' => 'whatsapp'],
            ];
        }

        return self::placed($method, $order);
    }

    /**
     * An order under way, the normal case. The title doesn't stay at "Pedido confirmado"
     * forever: the pointer lasts a day, so one comes back with the order ready or
     * delivered. In cash it isn't said to be paid, only under way.
     */
    private static function placed(string $method, array $order): array
    {
        $pickup = ($order['deliveryType'] ?? null) === 'pickup';
        $status = $order['status'] ?? null;

        if ($status === 'delivered') {
            return [
                'state' => 'success',
                'title' => 'Pedido entregado',
                'note' => 'Gracias por tu compra.',
                'showFolio' => true,
                'action' => ['kind' => 'whatsapp'],
            ];
        }

        if ($status === 'ready') {
            return [
                'state' => 'success',
                'title' => 'Tu pedido esta listo',
                'note' => $pickup ? 'Pasa por el local a recogerlo.' : 'Ya salio de la cocina.',
                'showFolio' => true,
                'action' => ['kind' => 'whatsapp'],
            ];
        }

        // The API accepts the six method/delivery combinations, so the delivery decides
        // the cash notice, not the method.
        $cashNote = $pickup ? ' Lo pagas en efectivo al recogerlo.' : ' Lo pagas en efectivo al recibirlo.';

        return [
            'state' => 'success',
            'title' => 'Pedido confirmado',
            'note' => 'Te avisaremos por WhatsApp cuando tu pedido este listo.'.($method === 'efectivo' ? $cashNote : ''),
            'showFolio' => true,
            'action' => ['kind' => 'whatsapp'],
        ];
    }
}
