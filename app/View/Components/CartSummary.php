<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The order summary card, DESKTOP ONLY: the second column of the cart (and, later, of
 * the delivery and payment screens), where the flow continues. Port of
 * src/components/CartSummary.astro; resources/js/lib/cart-summary.ts repaints it.
 *
 * It doesn't know which screen it's on: it gets the amounts already computed, the
 * shipping already resolved and where its button leads. The one thing it decides is the
 * total, because it can't receive it: a figure passed from outside could contradict its
 * own rows.
 */
class CartSummary extends Component
{
    public float|int $total;

    /**
     * @param  float|int  $subtotal  Products BEFORE discounts (the cart's `subtotal`).
     * @param  float|int  $discount  What promotions take off. With 0 the row is hidden.
     * @param  float|int  $products  Products already discounted (the cart's `total`).
     * @param  array{state: string, cost: float|int}  $shipping  Already resolved (App\Support\Shipping).
     * @param  float|int  $tip  The chosen tip; only on the payment screen.
     * @param  string|null  $href  Where the button leads. Without it, a submit of the form around it.
     * @param  bool  $disabled  Truly off: neither navigates nor submits.
     */
    public function __construct(
        public float|int $subtotal,
        public float|int $discount,
        public float|int $products,
        public array $shipping,
        public string $label,
        public float|int $tip = 0,
        public ?string $href = null,
        public bool $disabled = false,
    ) {
        // "Por cotizar" adds nothing: there's no quoted amount to add.
        $this->total = $products + $shipping['cost'] + $tip;
    }

    public function render(): View
    {
        return view('components.cart-summary');
    }
}
