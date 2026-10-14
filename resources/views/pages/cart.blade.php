<x-layout :store="$store" title="Mi pedido" description="Revisa tu pedido antes de pagar." noindex :vite="['resources/js/pages/cart.ts']">
    {{-- One header per screen: the 48px bar with the total on mobile, and from md up the
         store bar, the same as on the home and product pages. --}}
    <x-top-bar title="Mi pedido" :total="$total" mobile-only />

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

    {{-- The flex chain (page > main > form) keeps the action bar at the bottom of the
         screen even with few lines. --}}
    <main class="checkout-page d-flex flex-column flex-grow-1">
        {{-- The stack and the grid only exist on desktop (`display: contents` on mobile). --}}
        <div class="checkout-page__stack">
            <h1 class="checkout-page__title">Mi pedido</h1>

            {{-- The form also wraps the action bar, and on desktop it's the two-column grid,
                 so the card falls inside [data-order-summary] and the script repaints it. --}}
            <form
                class="checkout-page__grid"
                action="#"
                method="post"
                data-order-summary
                data-threshold="{{ $threshold }}"
                data-free-shipping="{{ $freeShipping['mode'] ?? 'none' }}"
                data-asset-base="{{ $assetBase }}"
            >
                {{-- Container 1: the order. --}}
                <div class="screen-body">
                    @if ($showProgress)
                        <x-shipping-progress :remaining="$remaining" :progress="$progress" />
                    @endif

                    {{-- Raw HTML and not a component per line: App\Support\CartView, the twin of
                         the browser's renderLines(). The list is replaced whole on each change. --}}
                    <ul @class(['order-list', 'list-unstyled', 'd-none' => $lines === []]) data-order-list>{!! $linesHtml !!}</ul>

                    @if ($unreachable)
                        {{-- 'manual' and not 'auto': /api/cart counts against the SESSION's limit,
                             where a 429 is caused by this same buyer; an automatic reload could
                             loop against their own cap. Outside the `data-empty` slot, which
                             order-summary.ts governs. --}}
                        <x-retry-notice body="No pudimos cargar tu pedido." :code="$cartCode" retry="manual" center />
                    @else
                        <p @class(['text-secondary', 'text-center', 'm-0', 'd-none' => $lines !== []]) data-empty>Tu carrito esta vacio.</p>
                    @endif

                    {{-- Filled by the script when the API rejects a change. --}}
                    <p class="order-list__error" role="alert" hidden data-cart-error></p>

                    {{-- Only with a discount (without it, the subtotal would repeat the total),
                         and only on mobile: on desktop the summary card has the whole account. --}}
                    <dl @class(['order-totals', 'd-md-none', 'd-none' => $discountTotal <= 0]) data-order-totals>
                        <div class="order-totals__row">
                            <dt class="order-totals__term">Subtotal</dt>
                            <dd class="order-totals__value" data-order-subtotal>{{ \App\Support\Price::format($subtotal) }}</dd>
                        </div>
                        <div class="order-totals__row order-totals__row--discount">
                            <dt class="order-totals__term">Descuentos</dt>
                            <dd class="order-totals__value" data-order-discount>− {{ \App\Support\Price::format($discountTotal) }}</dd>
                        </div>
                    </dl>

                    {{-- The divider and the comments are one piece. On desktop they go away
                         with an empty order (a note for an order that doesn't exist). --}}
                    <div @class(['checkout-page__notes', 'checkout-page__notes--empty' => $lines === []])>
                        <hr class="screen-body__divider" />

                        <x-order-comments :value="$draft['comments']" />
                    </div>
                </div>

                {{-- Three cases, and the last paints no bar: with lines, Continuar (grey if the
                     store closed); without lines and open, back to the menu; without lines and
                     closed, nothing (the floating card says why). Mobile only: on desktop the
                     action is the card's button. --}}
                @if ($lines !== [])
                    <div class="checkout-page__action">
                        <x-action-bar label="Continuar" href="/mamayaya/datos" :disabled="$closed" />
                    </div>
                @elseif (! $closed)
                    <div class="checkout-page__action">
                        <x-action-bar label="Ver el menu" href="/mamayaya" />
                    </div>
                @endif

                {{-- Container 2, desktop only. Always painted (also with an empty order):
                     it's the right column, and appearing with the first line would move the
                     whole screen. Its button can't continue without an order. --}}
                <x-cart-summary
                    :subtotal="$subtotal"
                    :discount="$discountTotal"
                    :products="$total"
                    :shipping="$shipping"
                    label="Continuar con la entrega"
                    href="/mamayaya/datos"
                    :disabled="$closed || $lines === []"
                />
            </form>
        </div>
    </main>

    @if ($closed && $schedule)
        <x-store-closed-notice :schedule="$schedule" />
    @endif

    {{-- The two sheets the desktop bar's pin and clock open; the schedule one is also
         opened by the closed-store card. --}}
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
