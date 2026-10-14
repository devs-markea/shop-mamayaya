<article @class(['menu-card', 'card', 'menu-card--no-media' => ! $image, 'menu-card--unavailable' => ! $orderable])>
    <div class="menu-card__body">
        <h3 class="product-card__title">
            {{-- stretched-link: the whole card is clickable, the "+" included. --}}
            <a class="stretched-link text-reset" href="{{ $href }}" title="{{ $product['name'] }}">{{ $displayName }}</a>

            @if ($promo)
                <x-promo-badge :label="$promo['label']" :name="$promo['name']" class="product-card__promo" />
            @endif
        </h3>

        <div class="menu-card__pricing">
            <span class="product-card__prices">
                <span class="product-card__from">Desde</span>
                <span class="product-card__price">{{ \App\Support\Price::format($promo['price'] ?? $product['basePrice']) }}</span>

                @if (isset($promo['original']))
                    <s class="price-was">{{ \App\Support\Price::format($promo['original']) }}</s>
                @endif
            </span>

            @if ($promo['note'] ?? null)
                <p class="product-card__deal">
                    <span class="promo-note">{{ $promo['note'] }}</span>
                </p>
            @endif
        </div>

        @if (($product['description'] ?? '') !== '')
            <p class="product-card__description">{{ $product['description'] }}</p>
        @endif
    </div>

    {{-- Skeleton until the image arrives, as in <x-product-card>. Without a photo there's
         no box: the text keeps the whole width. --}}
    @if ($image)
        <div class="menu-card__media skeleton" data-skeleton>
            <img class="menu-card__image" src="{{ $image['src'] }}" width="168" height="168" alt="{{ $image['alt'] }}" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" />
        </div>
    @endif

    <div class="menu-card__action">
        @if ($notice)
            <span class="tag tag--optional badge">{{ $notice }}</span>
        @else
            <span @class(['product-card__add', 'product-card__add--disabled' => ! $orderable]) aria-hidden="true">
                <x-icon name="plus" size="sm" />
            </span>
        @endif
    </div>
</article>
