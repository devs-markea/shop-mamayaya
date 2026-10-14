<article @class(['product-card', 'card', 'product-card--no-media' => ! $image, 'product-card--unavailable' => ! $orderable])>
    {{-- The photo box starts as a skeleton and drops it when the image arrives
         (resources/js/scripts/image-skeleton.ts). Without a photo there is no box at all. --}}
    @if ($image)
        <div class="product-card__media skeleton" data-skeleton>
            <img class="product-card__image" src="{{ $image['src'] }}" width="80" height="80" alt="{{ $image['alt'] }}" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" />

            {{-- After the image in the markup so it paints on top of it. --}}
            @if ($rank)
                <span class="product-card__rank">#{{ $rank }} favoritos</span>
            @endif
        </div>
    @endif

    <div class="product-card__body">
        <h3 class="product-card__title">
            {{-- stretched-link: the whole card is clickable, the "+" included, and so are the
                 unavailable ones (the product page exists). The title has the full name. --}}
            <a class="stretched-link text-reset" href="{{ $href }}" title="{{ $product['name'] }}">{{ $displayName }}</a>

            @if ($promo)
                <x-promo-badge :label="$promo['label']" :name="$promo['name']" class="product-card__promo" />
            @endif
        </h3>

        @if (($product['description'] ?? '') !== '')
            <p class="product-card__description">{{ $product['description'] }}</p>
        @endif

        <div class="product-card__footer">
            <div class="product-card__pricing">
                <span class="product-card__prices">
                    {{-- "Desde" only on desktop. Final price first, struck-through after: what
                         you pay is what you came to read. --}}
                    <span class="product-card__from">Desde</span>
                    <span class="product-card__price">{{ \App\Support\Price::format($promo['price'] ?? $product['basePrice']) }}</span>

                    @if (isset($promo['original']))
                        <s class="price-was">{{ \App\Support\Price::format($promo['original']) }}</s>
                    @endif
                </span>

                {{-- The note for a discount that doesn't reach every variant. The green
                     "Ahorra $X" label stays hidden for now: the price pair already says it. --}}
                @if ($promo['note'] ?? null)
                    <p class="product-card__deal">
                        <span class="promo-note">{{ $promo['note'] }}</span>
                    </p>
                @endif
            </div>

            {{-- The "+" or, if the dish can't be ordered for a reason of its own, that reason.
                 The "+" is a drawing inside the link's area, not a button. --}}
            @if ($notice)
                <span class="tag tag--optional badge">{{ $notice }}</span>
            @else
                <span @class(['product-card__add', 'product-card__add--disabled' => ! $orderable]) aria-hidden="true">
                    <x-icon name="plus" size="sm" />
                </span>
            @endif
        </div>
    </div>
</article>
