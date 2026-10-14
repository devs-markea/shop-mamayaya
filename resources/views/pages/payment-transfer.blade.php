<x-layout :store="$store" title="Pago por transferencia" description="Datos bancarios para completar tu pedido." noindex :vite="['resources/js/pages/payment-transfer.ts']">
    {{-- TWO SCREENS, ONE MARKUP. On mobile: the 124px header, the bank account, the
         notice, the order detail and "Confirmar pedido" in the fixed bottom bar. On
         desktop, two columns: what has to be read before paying on the left and the
         summary card on the right, where it's confirmed. The card takes over from the
         action bar: both are submits of the same form, so
         resources/js/scripts/confirm-order.ts closes the order the same from either. --}}
    <x-screen-header title="Pago por transferencia" back-href="/mamayaya/pago" mobile-only />

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

    {{-- The flex chain (page > main > form) keeps the action bar at the bottom. --}}
    <main class="checkout-page checkout-page--confirm d-flex flex-column flex-grow-1">
        {{-- The stack and the grid only exist on desktop (`display: contents` on mobile). --}}
        <div class="checkout-page__stack">
            <h1 class="checkout-page__title">Pago por transferencia</h1>

            {{-- data-method and data-next are the only things that tell this closing from
                 the cash one: the rest is confirm-order.ts, shared by both. --}}
            <form
                class="checkout-page__grid"
                action="#"
                method="post"
                data-confirm-form
                data-method="bank_transfer"
                data-next="/mamayaya/recibido"
                data-shipping="{{ $shipping['cost'] }}"
            >
                {{-- Container 1: what has to be read before transferring. --}}
                <div class="screen-body">
                    <x-bank-account :holder="$bankTransfer['holder']" :bank="$bankTransfer['bank']" :clabe="$bankTransfer['clabe']" />

                    <x-info-note>{{ $infoNote }}</x-info-note>

                    <x-order-detail
                        :items="$items"
                        :total="$total"
                        :subtotal="$subtotal"
                        :discount="$discount"
                        :tip="$tip"
                        :shipping="$shipping"
                    />

                    {{-- Filled by the script if checkout rejects the order. --}}
                    <x-form-error mark="data-checkout-error" />
                </div>

                {{-- Not painted on desktop: confirming is the card's button. --}}
                <div class="checkout-page__action">
                    <x-action-bar label="Confirmar pedido" />
                </div>

                {{-- Container 2, desktop only: the same card as the previous screens, here with
                     everything closed. Its button creates the order; where to go next is the
                     script's decision. --}}
                <x-cart-summary
                    :subtotal="$subtotal"
                    :discount="$discount"
                    :products="$products"
                    :shipping="$shipping"
                    :tip="$tip"
                    label="Confirmar pedido"
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
