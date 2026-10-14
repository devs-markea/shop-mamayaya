{{--
    A summary of what was ordered, with no actions: nothing is edited here. Port of
    src/components/OrderReceipt.astro.

    `items`: the order's lines (or the cart's): only quantity, name and, on desktop, the
    line amount are read. `method`: labelled with the short name (App\Support\Checkout).
    Mercado Pago's icon is provisional and cash leans on the same one.
--}}
@props(['items', 'total', 'method'])

@php
    $icon = ['bank_transfer' => 'phone', 'mercado_pago' => 'credit-card', 'efectivo' => 'credit-card'][$method];
@endphp

<div class="order-receipt">
    <x-section-label variant="soft">Resumen de tu pedido</x-section-label>

    {{-- Without lines the list isn't painted: an empty <ul> would leave the gap on both
         sides of nothing. --}}
    @if ($items !== [])
        <ul class="order-receipt__items list-unstyled">
            @foreach ($items as $item)
                <li class="order-receipt__item">
                    {{-- Quantity and name together; the group only exists on desktop. --}}
                    <span class="order-receipt__label">
                        <span class="order-receipt__quantity">×{{ $item['quantity'] }}</span>
                        <span class="order-receipt__name">{{ $item['name'] }}</span>
                    </span>

                    {{-- Desktop only, and only when the line carries its amount. --}}
                    @if (array_key_exists('lineTotal', $item))
                        <span class="order-receipt__price">{{ \App\Support\Price::format((float) $item['lineTotal']) }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <hr class="dashed-divider" />

    <div class="order-receipt__payment">
        <div class="order-receipt__method">
            <x-section-label variant="soft">Metodo de pago</x-section-label>

            <span class="order-receipt__method-name">
                <x-icon :name="$icon" class="order-receipt__method-icon" />

                {{-- Desktop only: there the label above isn't painted. --}}
                <span class="order-receipt__method-term d-none d-md-inline">Metodo de pago:</span>

                <span>{{ \App\Support\Checkout::PAYMENT_LABEL[$method] }}</span>
            </span>
        </div>

        <span class="order-receipt__total">{{ \App\Support\Price::format($total) }}</span>
    </div>
</div>
