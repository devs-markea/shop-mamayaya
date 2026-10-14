<x-layout :store="$store" :vite="['resources/js/pages/menu.ts']">
    <x-navbar
        :store="$store"
        :schedule="$schedule"
        :selected="$deliveryType"
        :cart-total="$cart['total'] ?? 0"
        :cart-count="$cart['itemsCount'] ?? 0"
    />

    {{-- flex-grow-1: the content grows, not the footer, so a two-dish store doesn't
         leave the footer floating with the grey background below it. --}}
    <main class="storefront flex-grow-1">
        {{-- A .container-fluid with two columns on desktop: the menu index on the left
             and, on the right, the banner, the bar and the catalog. On mobile both
             wrappers are `display: contents` and the index isn't painted. --}}
        <div class="storefront__stack container-fluid">
            <x-menu-sidebar :heading="$sidebarHeading" :sections="$sections" />

            <div class="storefront__content">
                <x-hero
                    :title="$banner['title']"
                    :description="$banner['description']"
                    :image="$banner['image']"
                    :status="$status"
                />

                <x-order-bar
                    :estimate="$store['delivery']['estimate']"
                    :shipping="$shippingLabel"
                    :pickup="$store['pickup']['estimate']"
                    :has-location="$hasLocation"
                    :selected="$deliveryType"
                    :search="$sections !== []"
                    :short-address="$store['location']['shortAddress']"
                >
                    {{-- The mobile index hangs from the anchored order bar. --}}
                    <x-category-nav :sections="$sections" sheet="sheet-menu" />
                </x-order-bar>

                {{-- The sheet the tabs' button opens. A <dialog>: it opens in the top
                     layer wherever it sits in the markup. --}}
                @if ($sections !== [])
                    <x-menu-sheet id="sheet-menu" :heading="$sidebarHeading" :shift="$shift" :sections="$sections" />
                @endif

                <section class="product-list" aria-label="Productos">
                    @if ($sections !== [])
                        {{-- The header of the desktop search results, which take the
                             sections' place while there is text (menu-search.ts). --}}
                        <header class="menu-results" hidden data-search-results>
                            <h2 class="menu-section__title">Resultados</h2>
                            <p class="menu-section__description" data-search-summary></p>
                        </header>

                        {{-- The same, for screen readers. Always present: a live region
                             that appears together with its text isn't announced. --}}
                        <p class="visually-hidden" aria-live="polite" data-search-status></p>

                        <div class="product-list__sections" data-product-list>
                            @foreach ($sections as $index => $section)
                                <x-menu-section :section="$section" :eager="$index === 0" :store-closed="$closed" />
                            @endforeach
                        </div>
                    @elseif ($catalogNotice)
                        {{-- An empty catalog is a 200 with nothing to retry; a failure has its retry. --}}
                        <div class="product-list__notice">
                            <x-retry-notice :body="$catalogNotice" :code="$catalogCode" :retry="$catalogRetry" />
                        </div>
                    @else
                        <p class="product-list__notice">Todavia no hay platillos publicados.</p>
                    @endif
                </section>
            </div>
        </div>

        {{-- The schedule sheet its button opens is mounted by the header. --}}
        @if ($closed && $schedule)
            <x-store-closed-notice :schedule="$schedule" />
        @endif

        {{-- The way to the order on mobile: the same cart as the desktop bar. --}}
        <x-cart-fab :count="$cart['itemsCount'] ?? 0" />
    </main>

    <x-footer :store="$store" :has-location="$hasLocation" :has-schedule="$hasSchedule" :schedule="$schedule" />
</x-layout>
