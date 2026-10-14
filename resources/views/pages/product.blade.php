{{-- An empty description goes as null so the layout uses the store's own. --}}
<x-layout
    :store="$store"
    :title="$product['name'] ?? 'Platillo no encontrado'"
    :dish="$product['name'] ?? null"
    :description="($product['description'] ?? '') ?: null"
    :vite="['resources/js/pages/product.ts']"
>
    {{-- Only without a photo: on mobile the header of this screen IS the photo, with its
         back control on top; without one, the page would have no way out. Hidden on
         desktop, where the bar's logo leads to the menu. --}}
    @if ($product && ! $image)
        <x-back-header back-href="/mamayaya" mobile-only />
    @endif

    {{-- The store bar, the same as the home page's. --}}
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

    <main class="product-page d-flex flex-column flex-grow-1">
        @if ($product)
            {{-- The grid only exists on desktop (`display: contents` on mobile). The
                 photo sets the modifier that splits it in two columns: without it there's
                 nothing to share and the page stays in the centered 720 column. --}}
            <div @class(['product-page__grid', 'product-page__grid--split' => $image])>
                @if ($image)
                    <x-product-hero :image="$image" />
                @endif

                <form
                    class="product-page__panel d-flex flex-column flex-grow-1"
                    method="post"
                    data-order-form
                    data-endpoint="/api/cart/items"
                    data-redirect="/mamayaya/carrito"
                    data-product-id="{{ $product['id'] }}"
                    @if ($variantId !== null) data-variant-id="{{ $variantId }}" @endif
                    data-base-price="{{ $basePrice }}"
                >
                    <div class="product-detail">
                        <div class="product-detail__intro">
                            {{-- The label goes on the name's row: separated from the title it
                                 reads like a loose fact. It wraps when it doesn't fit. --}}
                            <div class="product-detail__heading">
                                <h1 class="product-detail__title">{{ $product['name'] }}</h1>

                                @if ($promo)
                                    <x-promo-badge :label="$promo['detailLabel']" :name="$promo['name']" class="product-detail__promo" />
                                @endif
                            </div>

                            @if (($product['description'] ?? '') !== '')
                                <p class="product-detail__description">{{ $product['description'] }}</p>
                            @endif
                        </div>

                        @foreach ($groups as $group)
                            <hr class="product-detail__divider" />
                            <x-option-group :group="$group" />
                        @endforeach
                    </div>

                    @if ($unavailableNotice)
                        <p class="product-detail__notice">{{ $unavailableNotice }}</p>
                    @else
                        {{-- What doesn't fit next to a group: the request's entries cap and the
                             422 errors that don't name a customization. --}}
                        <p class="product-detail__notice product-detail__notice--error" role="alert" hidden data-form-error></p>
                    @endif

                    {{-- The buy row. On mobile it isn't a row: the wrapper paints nothing and
                         the button stays the fixed bottom bar, with the counter hidden. With
                         the store closed it disappears: the page stays a page to read. --}}
                    @unless ($closed)
                        <div class="product-page__buy">
                            @if ($product['available'])
                                <x-product-counter :item-name="$product['name']" />
                                <x-action-bar label="Agregar — {total}" :total="$total" />
                            @else
                                <x-action-bar label="Volver al menu" href="/mamayaya" />
                            @endif
                        </div>
                    @endunless
                </form>
            </div>
        @else
            {{-- Also the store's 404: anything under /mamayaya that isn't a screen lands
                 here. Labelled as a dish because that's what this route is asked for, and
                 the way out (back to the menu) works for both. --}}
            <div class="product-detail">
                <div class="product-detail__intro">
                    <h1 class="product-detail__title">{{ $noticeTitle }}</h1>
                    <x-retry-notice :body="$noticeBody" :code="$noticeCode" :retry="$noticeRetry" />
                </div>
            </div>

            <div class="product-page__buy">
                <x-action-bar label="Volver al menu" href="/mamayaya" />
            </div>
        @endif
    </main>

    {{-- The two sheets the bar's pin and clock open, mounted by this page (like the
         navbar does on the home page) so their labels are buttons and not dead text. --}}
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
