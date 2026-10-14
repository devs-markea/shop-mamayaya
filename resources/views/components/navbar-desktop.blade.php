<header class="navbar-desktop">
    <div class="navbar-desktop__inner">
        <a class="navbar-desktop__brand" href="/mamayaya">
            <x-brand-logo class="navbar-desktop__logo" />
        </a>

        {{-- `data-delivery`: the mode the page arrives with. It decides which estimate
             reads where there's no switch to say it. --}}
        <div class="navbar-desktop__store" data-delivery="{{ $selected }}">
            @foreach ($metas as $meta)
                <button
                    @class(['navbar-desktop__meta', 'navbar-desktop__meta--truncate' => $meta['truncate'], 'btn-plain'])
                    type="button"
                    @if ($name = $metaName($meta)) aria-label="{{ $name }}" @endif
                    aria-haspopup="dialog"
                    data-sheet="{{ $meta['sheet'] }}"
                >
                    <x-icon :name="$meta['icon']" />

                    <span class="navbar-desktop__meta-value" @if ($meta['truncate']) title="{{ $meta['label'] }}" @endif>{{ $meta['label'] }}</span>
                </button>
            @endforeach

            @foreach ($estimates as $item)
                <span class="navbar-desktop__meta" data-mode="{{ $item['mode'] }}">
                    <x-icon :name="$item['icon']" />

                    <span class="navbar-desktop__meta-value">
                        <span class="visually-hidden">{{ $item['name'] }}: </span>{{ $item['value'] }}
                    </span>
                </span>
            @endforeach

            <a class="navbar-desktop__cart" href="/mamayaya/carrito" aria-label="{{ $cartLabel }}" data-cart-chip>
                <span class="navbar-desktop__cart-total" data-cart-chip-total>{{ \App\Support\Price::format($cartTotal) }}</span>

                {{-- Positioning context the exact size of the icon: the count hangs from
                     its corner, not from the chip. --}}
                <span class="navbar-desktop__cart-icon">
                    <x-icon name="shopping-cart" class="navbar-desktop__cart-glyph" />

                    {{-- Always painted and hidden with `hidden` when empty: the cart page
                         updates it without reloading. --}}
                    <span class="navbar-desktop__cart-count" aria-hidden="true" @if ($cartCount <= 0) hidden @endif data-cart-chip-count>{{ $cartCount }}</span>
                </span>
            </a>
        </div>
    </div>
</header>
