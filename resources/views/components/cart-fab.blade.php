{{--
    The floating cart button: the way to the order on MOBILE (from md up the desktop
    bar's chip replaces it; the block hides itself). Its accessible name says the units,
    not the amount. Port of src/components/CartFab.astro.
--}}
@props(['count' => 0])

@php($label = $count > 0 ? "Mi pedido: {$count} ".($count === 1 ? 'articulo' : 'articulos') : 'Mi pedido, vacio')

<a class="cart-fab" href="/mamayaya/carrito" aria-label="{{ $label }}">
    {{-- Positioning context the exact size of the icon, as in the desktop bar. --}}
    <span class="cart-fab__icon">
        <x-icon name="shopping-cart" class="cart-fab__glyph" />

        <span class="cart-fab__count" aria-hidden="true" @if ($count <= 0) hidden @endif>{{ $count }}</span>
    </span>
</a>
