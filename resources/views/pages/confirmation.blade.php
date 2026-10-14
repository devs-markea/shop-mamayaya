<x-layout :store="$store" :title="$status['title']" :description="$description" noindex :vite="['resources/js/pages/confirmation.ts']">
    {{-- Back to the menu and not to the payment screen: the order is placed, and going back
         would invite confirming it again (retrying a Mercado Pago charge is this screen's
         button, on the same order). One header per screen: the 64px one on mobile, and
         from md up the store bar, where the logo leads back to the menu. --}}
    <x-back-header back-href="/mamayaya" mobile-only />

    <x-navbar-desktop
        :has-location="$hasLocation"
        :has-schedule="$hasSchedule"
        :shift="$shift"
        :estimate="$store['delivery']['estimate']"
        :pickup="$store['pickup']['estimate']"
        :selected="$deliveryType"
        :cart-total="$cart['total'] ?? 0"
        :cart-count="$cart['itemsCount'] ?? 0"
    />

    {{-- The flex chain (page > main) keeps the action bar at the bottom. --}}
    <main class="confirmation-page d-flex flex-column flex-grow-1">
        @if ($retry)
            {{-- The form only wraps the content when the action is charging: it's a submit,
                 and its error notice has to be inside. --}}
            <form class="d-flex flex-column flex-grow-1" action="#" method="post" data-retry-form data-order="{{ $order['id'] ?? '' }}">
                <div class="screen-body screen-body--confirmation">
                    <x-order-status :title="$status['title']" :note="$status['note']" :folio="$status['folio']" :state="$status['state']" />

                    <x-order-receipt :items="$items" :total="$total" method="mercado_pago" />

                    {{-- Filled by the script if the gateway or the API reject the charge. --}}
                    <x-form-error mark="data-checkout-error" />

                    {{-- Last sub-container, desktop only: the same submit as the bar (a new
                         charge on this order), so it has to stay inside the form. --}}
                    <x-confirmation-cta :label="$action['label']" :href="$action['href'] ?? null" :icon="$action['icon'] ?? null" :variant="$action['variant'] ?? 'primary'" :note="$actionNote" />
                </div>

                {{-- Not painted on desktop: the wrapper removes it from md up. --}}
                <div class="confirmation-page__action">
                    <x-action-bar :label="$action['label']" :href="$action['href'] ?? null" :icon="$action['icon'] ?? null" :variant="$action['variant'] ?? 'primary'" :note="$actionNote" />
                </div>
            </form>
        @else
            {{-- data-awaiting-payment: the script reloads the screen a few times while the
                 webhook arrives, so the state resolves without the buyer pressing anything. --}}
            <div class="screen-body screen-body--confirmation" @if ($awaiting) data-awaiting-payment="" @endif>
                <x-order-status :title="$status['title']" :note="$status['note']" :folio="$status['folio']" :state="$status['state']" />

                @if ($view !== null)
                    <x-order-receipt :items="$items" :total="$total" method="mercado_pago" />
                @else
                    {{-- What's left to do, numbered: the same card in the three closings
                         without a gateway; the copy changes, not the piece. --}}
                    <x-steps :steps="$steps" />

                    {{-- The data needed to complete the payment without going back. Without a
                         CLABE there's nothing to copy; in cash no card is painted. --}}
                    @if (! $cash && ! empty($store['bankTransfer']['clabe']))
                        <x-clabe-card :bank="$store['bankTransfer']['bank']" :clabe="$store['bankTransfer']['clabe']" />
                    @endif
                @endif

                {{-- Last sub-container, desktop only: it takes over from the bottom bar, with
                     the same values on purpose (the width changes the shape, not the deed). --}}
                <x-confirmation-cta :label="$action['label']" :href="$action['href'] ?? null" :icon="$action['icon'] ?? null" :variant="$action['variant'] ?? 'primary'" :note="$actionNote" :secondary="$statusAction" />
            </div>

            {{-- The same action in the mobile fixed bar: WhatsApp when nothing is left to pay
                 in the store, rereading this screen while a payment is being credited. Under
                 it, when the business wrote its message, "Ver estado de pedido". --}}
            <div class="confirmation-page__action">
                <x-action-bar :label="$action['label']" :href="$action['href'] ?? null" :icon="$action['icon'] ?? null" :variant="$action['variant'] ?? 'primary'" :note="$actionNote" :secondary="$statusAction" />
            </div>
        @endif
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

    {{-- The same device fingerprint as /mamayaya/pago, and here ONLY when the screen offers
         to pay: `pay` is the only state that opens a new charge. --}}
    @if ($retry)
        <x-device-fingerprint />
    @endif
</x-layout>
