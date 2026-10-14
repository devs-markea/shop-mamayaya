<x-layout :store="$store" title="Datos de entrega" description="Indica donde y como quieres recibir tu pedido." noindex :vite="['resources/js/pages/delivery.ts']">
    {{-- One header per screen: the 124px one with the back control on mobile, and from md
         up the store bar. --}}
    <x-screen-header title="Datos de entrega" back-href="/mamayaya/carrito" mobile-only />

    <x-navbar-desktop
        :has-location="$hasLocation"
        :has-schedule="$hasSchedule"
        :shift="$shift"
        :estimate="$store['delivery']['estimate']"
        :pickup="$store['pickup']['estimate']"
        :selected="$deliveryType"
        :cart-total="$total"
        :cart-count="$cart['itemsCount'] ?? 0"
    />

    {{-- The flex chain (page > main > form) keeps the action bar at the bottom. --}}
    <main class="checkout-page d-flex flex-column flex-grow-1">
        {{-- The stack and the grid only exist on desktop (`display: contents` on mobile). --}}
        <div class="checkout-page__stack">
            <h1 class="checkout-page__title">Datos de entrega</h1>

            <form
                class="checkout-page__grid"
                action="#"
                method="post"
                data-delivery-form
                data-free-shipping="{{ $freeShipping['mode'] ?? 'none' }}"
                data-threshold="{{ $threshold }}"
                data-products="{{ $total }}"
                data-subtotal="{{ $cart['subtotal'] ?? 0 }}"
                data-discount="{{ $cart['discountTotal'] ?? 0 }}"
                @if ($testMode) data-test-mode @endif
            >
                {{-- Container 1: the delivery form. --}}
                <div class="screen-body">
                    {{-- 1. Delivery mode: the same switch as the listing. --}}
                    <x-delivery-switch :selected="$deliveryType" />

                    {{-- The fields as one block under the switch (only groups on desktop). --}}
                    <div class="checkout-page__fields">
                        {{-- 2. Contact. Name and phone are ALWAYS asked, pickup included: the
                             phone is the key the ERP identifies the customer by. --}}
                        <div class="checkout-page__contact d-flex flex-column">
                            <x-text-field name="name" label="Nombre completo" placeholder="Nombre completo" autocomplete="name" required />
                            <x-phone-field />
                        </div>

                        {{-- 3. Where to deliver: delivery only. Both the written address (what the
                             courier reads) and the point (what measures shipping) are asked;
                             without a location you can't continue (delivery-form.ts). --}}
                        <div class="checkout-page__address d-flex flex-column" @if ($deliveryType === 'pickup') hidden @endif data-address-block>
                            {{-- The group's label, desktop only: on mobile <x-location-picker>
                                 says "Ubicacion". --}}
                            <h2 class="field-label d-none d-md-block">Direccion</h2>

                            <x-location-picker :maps-key="$mapsKey" />

                            {{-- Where the store delivers, when the shared point falls outside.
                                 Inside the address block on purpose: it goes away with it. --}}
                            <x-info-note :hidden="$areaNotice === ''" mark="data-area-notice" mark-text="data-area-notice-text">{{ $areaNotice }}</x-info-note>

                            <x-address-fields :required="$deliveryType !== 'pickup'" />
                        </div>
                    </div>

                    {{-- What's missing to continue, filled by the script when trying to move on:
                         the browser's bubbles close at the first touch. --}}
                    <x-form-error mark="data-delivery-error" />
                </div>

                {{-- Not painted on desktop: continuing is the card's button. Both are submits
                     of the same form, so the script validates the same from either. --}}
                <div class="checkout-page__action">
                    <x-action-bar label="Continuar" />
                </div>

                {{-- Container 2, desktop only: the same card as the cart. Without `href` its
                     button is a submit, because continuing starts by validating. --}}
                <x-cart-summary
                    :subtotal="$cart['subtotal'] ?? 0"
                    :discount="$cart['discountTotal'] ?? 0"
                    :products="$total"
                    :shipping="$shipping"
                    label="Continuar con el pago"
                />
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
</x-layout>
