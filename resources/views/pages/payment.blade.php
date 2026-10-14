<x-layout :store="$store" title="Resumen de pago" description="Revisa el importe y elige como pagar." noindex :vite="['resources/js/pages/payment.ts']">
    {{-- TWO SCREENS, ONE MARKUP. On mobile: the 124px header, the tip, the methods, the
         breakdown and "Continuar" in the fixed bottom bar. On desktop it's built like the
         cart and the delivery data, split in two columns: what has to be chosen on the
         left and the summary card on the right, which is where one continues.

         With the card, the body's breakdown (<x-payment-summary>) stays on mobile: it's
         the same account told twice. Same for the legal note: what's accepted on
         continuing is read next to the control that continues. --}}
    <x-screen-header title="Resumen de pago" back-href="/mamayaya/datos" mobile-only />

    <x-navbar-desktop
        :has-location="$hasLocation"
        :has-schedule="$hasSchedule"
        :shift="$shift"
        :estimate="$store['delivery']['estimate']"
        :pickup="$store['pickup']['estimate']"
        :selected="$deliveryType"
        :cart-total="$products"
        :cart-count="$cart['itemsCount'] ?? 0"
    />

    <main class="checkout-page d-flex flex-column flex-grow-1">
        {{-- The stack and the grid only exist on desktop (`display: contents` on mobile). --}}
        <div class="checkout-page__stack">
            <h1 class="checkout-page__title">Resumen de pago</h1>

            <form
                class="checkout-page__grid"
                action="#"
                method="post"
                data-payment-form
                data-products="{{ $products }}"
                data-shipping="{{ $shipping['cost'] }}"
                data-shipping-state="{{ $shipping['state'] }}"
                data-subtotal="{{ $cart['subtotal'] ?? 0 }}"
                data-discount="{{ $cart['discountTotal'] ?? 0 }}"
            >
                {{-- Container 1: what has to be chosen. --}}
                <div class="screen-body">
                    @if ($showTips)
                        <x-tip-selector :selected="$tip" :amounts="$tipAmounts" />
                    @endif

                    <x-payment-methods :selected="$method" :delivery-type="$deliveryType" :has-bank-transfer="$hasBankTransfer" />

                    {{-- Mobile only: on desktop the card on the right closes this same account,
                         with the button inside. --}}
                    <div class="d-md-none">
                        <x-payment-summary :products="$products" :tip="$tip" :shipping="$shipping" />
                    </div>

                    {{-- Filled by the script if checkout rejects the order. --}}
                    <x-form-error mark="data-checkout-error" />

                    {{-- Also mobile only: on desktop the note goes inside the card, next to the
                         button that triggers it. --}}
                    <div class="d-md-none">
                        <x-legal-note :terms-url="$store['legal']['termsUrl']" />
                    </div>
                </div>

                {{-- A plain button, not WhatsApp's: it continues the flow (transfer leads to
                     the account, cash to where to pick up, Mercado Pago closes the order).
                     Not painted on desktop: continuing is the card's button. Both are
                     submits of the same form, so the script decides the same from either. --}}
                <div class="checkout-page__action">
                    <x-action-bar label="Continuar" />
                </div>

                {{-- Container 2, desktop only: the same card as the cart and the delivery data,
                     here with the tip. Without `href` its button is a submit, because where it
                     goes depends on the method just chosen. --}}
                <x-cart-summary
                    :subtotal="$cart['subtotal'] ?? 0"
                    :discount="$cart['discountTotal'] ?? 0"
                    :products="$products"
                    :shipping="$shipping"
                    :tip="$tip"
                    label="Confirmar el pago"
                >
                    {{-- The legal note, on desktop, between the total and the button that
                         accepts it. --}}
                    <x-slot:note>
                        <x-legal-note :terms-url="$store['legal']['termsUrl']" />
                    </x-slot:note>
                </x-cart-summary>
            </form>
        </div>
    </main>

    {{-- The two sheets the desktop bar's pin and clock open. --}}
    @if ($hasSchedule)
        <x-schedule-sheet :schedule="$schedule" />
    @endif

    @if ($hasLocation)
        <x-location-sheet
            :message="$store['location']['message']"
            :address="$store['location']['address']"
            :maps-url="$store['location']['mapsUrl']"
        />
    @endif

    <x-footer :store="$store" :has-location="$hasLocation" :has-schedule="$hasSchedule" :schedule="$schedule" />

    {{-- Mercado Pago's device fingerprint: the checkout's charge is born here. --}}
    <x-device-fingerprint />
</x-layout>
