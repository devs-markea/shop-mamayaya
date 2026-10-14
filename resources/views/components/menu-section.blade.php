<section
    class="menu-section menu-section--{{ $section['kind'] }}"
    id="{{ $section['anchor'] }}"
    aria-labelledby="{{ $titleId }}"
    data-menu-section
    @if ($featured) data-menu-featured @endif
>
    <header class="menu-section__head">
        <div class="menu-section__heading">
            <div class="menu-section__title-row">
                <h2 class="menu-section__title" id="{{ $titleId }}">{{ $section['name'] }}</h2>
                @unless ($featured)
                    <span class="menu-section__count">{{ $countLabel }}</span>
                @endunless
            </div>
            @if ($section['description'] !== '')
                <p class="menu-section__description">{{ $section['description'] }}</p>
            @endif
        </div>

        {{-- They arrive disabled: without JS they'd do nothing, and the list still scrolls
             natively. The script turns them on depending on what's left to see. --}}
        @if ($featured)
            <div class="menu-section__controls">
                <button class="menu-section__arrow btn-plain" type="button" aria-label="Destacados anteriores" aria-controls="{{ $itemsId }}" disabled data-carousel-prev>
                    <x-icon name="arrow-sm-left" />
                </button>
                <button class="menu-section__arrow btn-plain" type="button" aria-label="Destacados siguientes" aria-controls="{{ $itemsId }}" disabled data-carousel-next>
                    <x-icon name="arrow-sm-right" />
                </button>
            </div>
        @endif
    </header>

    {{-- Featured dishes go inside their own container: on mobile it's the carousel's
         (Swiper takes the container, the list is the track and each dish a slide). --}}
    @if ($featured)
        <div class="menu-section__carousel" data-featured-carousel>
            <ul class="menu-section__items menu-section__track list-unstyled mb-0" id="{{ $itemsId }}" data-featured-track>
                @foreach ($section['products'] as $index => $product)
                    <li class="menu-section__item">
                        <x-product-card
                            :product="$product"
                            :eager="$eager && $index === 0"
                            :store-closed="$storeClosed"
                            :rank="$index < $ranked ? $index + 1 : null"
                        />
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <ul class="menu-section__items menu-section__grid list-unstyled mb-0" id="{{ $itemsId }}">
            @foreach ($section['products'] as $index => $product)
                <li class="menu-section__item" data-search="{{ $searchText($product) }}">
                    <x-menu-card :product="$product" :eager="$eager && $index === 0" :store-closed="$storeClosed" />
                </li>
            @endforeach
        </ul>
    @endif

    {{-- The mobile carousel's dots: Swiper paints them here. Empty takes no room. --}}
    @if ($featured)
        <div class="menu-section__dots" data-featured-dots></div>
    @endif
</section>
