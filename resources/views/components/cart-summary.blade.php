<aside class="cart-summary" aria-labelledby="cart-summary-title" data-cart-summary>
    <h2 class="cart-summary__title" id="cart-summary-title">Resumen de compra</h2>

    <dl class="cart-summary__rows">
        <div class="cart-summary__row">
            <dt class="cart-summary__term">Subtotal</dt>
            <dd class="cart-summary__value" data-cart-subtotal>{{ \App\Support\Price::format($subtotal) }}</dd>
        </div>

        {{-- Always in the markup (hidden with d-none): a quantity change can start or undo
             a discount, and the script shows and hides it without reloading. --}}
        <div @class(['cart-summary__row', 'd-none' => $discount <= 0]) data-cart-discount-row>
            <dt class="cart-summary__term">Descuentos</dt>
            <dd class="cart-summary__value cart-summary__value--save" data-cart-discount>− {{ \App\Support\Price::format($discount) }}</dd>
        </div>

        {{-- Pickup has no shipping and the row goes whole; hidden and not omitted, because
             on the delivery screen the mode is chosen right here. --}}
        <div @class(['cart-summary__row', 'd-none' => $shipping['state'] === 'none']) data-cart-shipping-row>
            <dt class="cart-summary__term">Costo de envio</dt>

            {{-- The state goes in data-state: the script changes it in one assignment and
                 CSS sets color and weight. --}}
            <dd class="cart-summary__value" data-state="{{ $shipping['state'] }}" data-cart-shipping>{{ \App\Support\Shipping::label($shipping) }}</dd>
        </div>

        {{-- Only where it's chosen (payment), and only when something was left. --}}
        <div @class(['cart-summary__row', 'd-none' => $tip <= 0]) data-cart-tip-row>
            <dt class="cart-summary__term">Propina</dt>
            <dd class="cart-summary__value" data-cart-tip>{{ \App\Support\Price::format($tip) }}</dd>
        </div>

        <hr class="cart-summary__divider" />

        <div class="cart-summary__row">
            <dt class="cart-summary__total-term">Total</dt>
            <dd class="cart-summary__total-value" data-cart-total>{{ \App\Support\Price::format($total) }}</dd>
        </div>
    </dl>

    {{-- Room between the account and the button for what a screen has to say before
         continuing (the payment screen's legal note). Without it, nothing is painted. --}}
    {{ $note ?? '' }}

    @if ($disabled)
        {{-- <button disabled> and not a dimmed link. `type="button"` so it doesn't submit. --}}
        <button class="cart-summary__action btn btn-primary" type="button" disabled>{{ $label }}</button>
    @elseif ($href)
        <a class="cart-summary__action btn btn-primary" href="{{ $href }}">{{ $label }}</a>
    @else
        <button class="cart-summary__action btn btn-primary" type="submit">{{ $label }}</button>
    @endif
</aside>
