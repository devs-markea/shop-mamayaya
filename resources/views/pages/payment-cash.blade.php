<x-layout :store="$store" title="Pago en efectivo" description="Confirma tu pedido y pagalo en efectivo al recibirlo." noindex :vite="['resources/js/pages/payment-cash.ts']">
    {{-- TWO SCREENS, ONE MARKUP, for the same reason as the transfer: on mobile the 124px
         header, how it's paid, the notice, the order detail and "Confirmar pedido" in the
         fixed bottom bar; on desktop two columns with the summary card on the right,
         where it's confirmed. --}}
    <x-screen-header title="Pago en efectivo" back-href="/mamayaya/pago" mobile-only />

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
            <h1 class="checkout-page__title">Pago en efectivo</h1>

            {{-- The same closing as the transfer and so the same script: what changes is
                 what has to be read before confirming, not what's sent. --}}
            <form
                class="checkout-page__grid"
                action="#"
                method="post"
                data-confirm-form
                data-method="efectivo"
                data-next="/mamayaya/recibido"
                data-shipping="{{ $shipping['cost'] }}"
            >
                {{-- Container 1: what has to be read before paying. --}}
                <div class="screen-body">
                    {{-- The desktop sentence says the same as the mobile one, whole and in a
                         single line: there the card stays in two rows and the sentence below
                         retires. --}}
                    <x-payment-method-card
                        icon="cash"
                        title="Pago contra entrega"
                        note="Efectivo en mano"
                        wide-note="El pago se realizara en efectivo directamente al repartidor al momento de la entrega o en mostrador si es para recoger."
                        description="Realiza tu pago directamente al repartidor al recibir tu pedido en tu domicilio, o en caja al recoger en sucursal."
                    />

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
