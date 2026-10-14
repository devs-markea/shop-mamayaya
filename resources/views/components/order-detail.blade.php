@use('App\Support\Price')
@use('App\Support\Shipping')

<div class="order-detail">
    <x-section-label>Detalle del pedido</x-section-label>

    @if ($lines !== [])
        <ul class="order-detail__items list-unstyled">
            @foreach ($lines as ['item' => $item, 'image' => $image, 'selection' => $selection])
                <li class="order-detail__item">
                    {{-- Desktop only. Without a photo there's no box. `loading="lazy"` is what
                         keeps a phone from paying for a photo inside a `display: none`. --}}
                    @if ($image)
                        <span class="order-detail__media">
                            <img class="order-detail__image" src="{{ $image['src'] }}" width="56" height="56" alt="{{ $image['alt'] }}" loading="lazy" decoding="async" />
                        </span>
                    @endif

                    <span class="order-detail__line">
                        <span class="order-detail__head">
                            <span class="order-detail__info">
                                <span class="order-detail__name">{{ $item['quantity'] }}× {{ $item['name'] }}</span>

                                @if (! empty($item['promotion']['label']))
                                    <span class="promo-badge promo-badge--sm">
                                        <span class="promo-badge__label">{{ $item['promotion']['label'] }}</span>
                                    </span>
                                @endif
                            </span>

                            {{-- Desktop only: on mobile the line carries no amounts. --}}
                            <span class="order-detail__price">{{ Price::format($item['lineTotal']) }}</span>
                        </span>

                        @if ($selection !== '')
                            <span class="order-detail__custom">{{ $selection }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="order-detail__item">Tu pedido esta vacio.</p>
    @endif

    <hr class="dashed-divider" />

    @if ($breakdown)
        <dl class="order-detail__rows">
            <div class="order-detail__row">
                <dt class="order-detail__term">Subtotal</dt>
                <dd class="order-detail__value">{{ Price::format($subtotal) }}</dd>
            </div>

            @if ($discount > 0)
                <div class="order-detail__row order-detail__row--discount">
                    <dt class="order-detail__term">Descuentos</dt>
                    <dd class="order-detail__value">− {{ Price::format($discount) }}</dd>
                </div>
            @endif

            @if ($shipping['state'] !== 'none')
                <div class="order-detail__row">
                    {{-- The hint glued to the text, as in <x-payment-summary>. --}}
                    <dt class="order-detail__term">
                        @if ($shipping['state'] === 'quoted' && ($shipping['km'] ?? null) !== null)
                            Envio<span class="order-detail__hint">{{ Shipping::formatKm($shipping['km']) }}</span>
                        @else
                            Envio
                        @endif
                    </dt>

                    @if ($shipping['state'] === 'quoted')
                        <dd class="order-detail__value">{{ Price::format($shipping['cost']) }}</dd>
                    @elseif ($shipping['state'] === 'free')
                        <dd class="order-detail__value order-detail__value--free">Gratis</dd>
                    @else
                        <dd class="order-detail__value order-detail__value--pending">Por cotizar</dd>
                    @endif
                </div>
            @endif

            @if ($tip > 0)
                <div class="order-detail__row">
                    <dt class="order-detail__term">Propina</dt>
                    <dd class="order-detail__value">{{ Price::format($tip) }}</dd>
                </div>
            @endif
        </dl>
    @endif

    <div class="order-detail__total">
        <span class="order-detail__total-term">Total a pagar</span>
        <span class="order-detail__total-value">{{ Price::format($total) }}</span>
    </div>
</div>
