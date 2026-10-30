{{--
    The home page's order bar: on mobile the switch and the metas (anchored, with the
    category tabs hanging from it through the slot); on desktop the search and the
    switch in one row. Port of src/components/OrderBar.astro.

    `selected` comes from the checkout draft and decides which pair of metas reads: CSS
    does the swap reading the checked radio, so the bar arrives right without any JS.
--}}
@props([
    'estimate' => '',
    'shipping' => null,
    'pickup' => '',
    'hasLocation' => false,
    'selected' => 'delivery',
    'search' => false,
    'shortAddress' => '',
])

<section class="order-bar" aria-label="Opciones de entrega">
    <div class="order-bar__inner">
        {{-- First in the markup because on desktop it goes on the left. Not painted on
             mobile, where the search lives below the tabs (category-nav). --}}
        @if ($search)
            <x-menu-search />
        @endif

        <x-delivery-switch :selected="$selected" />

        <x-order-meta
            :estimate="$estimate"
            :shipping="$shipping"
            :pickup="$pickup"
            :has-location="$hasLocation"
            :short-address="$shortAddress"
        />
    </div>

    {{ $slot }}
</section>
