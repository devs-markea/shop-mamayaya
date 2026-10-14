{{--
    The breakdown of /mamayaya/pago on mobile: products, shipping and total. Port of
    src/components/PaymentSummary.astro. The total is computed, never written, so the
    view can't contradict the data; resources/js/scripts/payment-summary.ts recomputes it
    when the tip changes.

    `shipping` is already resolved (App\Support\Shipping), and each state paints its own:
    `none` (pickup) drops the row, `free` says "Gratis", `quoted` the amount with the
    distance, and `pending` "Por cotizar".
--}}
@props(['products', 'tip', 'shipping' => ['state' => 'pending', 'cost' => 0]])

<div class="summary">
    <x-section-label>Resumen</x-section-label>

    <dl class="summary__rows">
        <div class="summary__row">
            <dt class="summary__term">Productos</dt>
            <dd class="summary__value">{{ \App\Support\Price::format($products) }}</dd>
        </div>

        @if ($shipping['state'] !== 'none')
            <div class="summary__row">
                {{-- The distance next to the concept: it's where the amount comes from. Glued to
                     the text, as Astro renders it (a space would widen the hint's margin), and a
                     Blade directive can't follow a letter: hence the label in both branches. --}}
                <dt class="summary__term">
                    @if ($shipping['state'] === 'quoted' && ($shipping['km'] ?? null) !== null)
                        Costo de envio<span class="summary__hint">{{ \App\Support\Shipping::formatKm($shipping['km']) }}</span>
                    @else
                        Costo de envio
                    @endif
                </dt>

                @if ($shipping['state'] === 'quoted')
                    <dd class="summary__value">{{ \App\Support\Price::format($shipping['cost']) }}</dd>
                @elseif ($shipping['state'] === 'free')
                    <dd class="summary__value summary__value--free">Gratis</dd>
                @else
                    <dd class="summary__value summary__value--pending">Por cotizar</dd>
                @endif
            </div>
        @endif

        <hr class="summary__divider" />

        <div class="summary__row">
            <dt class="summary__total-term">Total</dt>
            <dd class="summary__total-value" data-summary-total>{{ \App\Support\Price::format($products + $tip + $shipping['cost']) }}</dd>
        </div>
    </dl>
</div>
