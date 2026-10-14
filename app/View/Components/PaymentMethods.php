<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The payment methods of /mamayaya/pago. Port of src/components/PaymentMethods.astro.
 *
 * The delivery mode decides WHICH methods exist, not only which one comes checked:
 * pickup only has cash (paid at the store); delivery has the transfer (only with a
 * published account) and Mercado Pago. Cash on delivery is hidden: see CASH_ON_DELIVERY.
 *
 * The `value`s are the design's, not the contract's: toPaymentMethod() translates them
 * when the order is closed.
 */
class PaymentMethods extends Component
{
    /**
     * Cash on delivery, hidden for now: paying the courier isn't offered. Switched off
     * here and not deleted because the rest of the path still stands (/mamayaya/pago/efectivo,
     * the acknowledgement and the templates), so offering it again is changing this line.
     */
    public const CASH_ON_DELIVERY = false;

    public bool $pickup;

    public bool $transfer;

    public bool $cashOnDelivery = self::CASH_ON_DELIVERY;

    /** The one that comes checked: the chosen one if it's still on screen, else the first offered. */
    public string $checked;

    /**
     * @param  string|null  $selected  The method already chosen, when coming back from payment.
     * @param  'delivery'|'pickup'  $deliveryType
     * @param  bool  $hasBankTransfer  Whether the business published its bank account.
     */
    public function __construct(
        public ?string $selected = 'bank_transfer',
        public string $deliveryType = 'delivery',
        public bool $hasBankTransfer = true,
    ) {
        $this->pickup = $deliveryType === 'pickup';
        $this->transfer = ! $this->pickup && $hasBankTransfer;

        // In the order they're painted. Leaving the group unchecked would send whoever
        // touched nothing to "Elige un metodo de pago." over an option they never saw.
        $offered = [
            ...($this->transfer ? ['bank_transfer'] : []),
            ...(self::CASH_ON_DELIVERY ? ['efectivo'] : []),
            'mercado_pago',
        ];

        $this->checked = $selected !== null && in_array($selected, $offered, true) ? $selected : $offered[0];
    }

    public function render(): View
    {
        return view('components.payment-methods');
    }
}
