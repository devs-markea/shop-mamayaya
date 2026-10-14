{{--
    The 48px bar of the order screen: back control, title and the order total on the
    right. The title is "centered between its neighbours, not in the layout". Port of
    src/components/TopBar.astro; the .topbar block lives in back-button.css.

    `mobileOnly`: hidden from md up, where the screen has the desktop store bar.
--}}
@props(['title', 'total', 'backHref' => '/mamayaya', 'mobileOnly' => false])

<header @class(['topbar', 'topbar--mobile' => $mobileOnly])>
    <x-back-button :href="$backHref" />

    <h1 class="topbar__title">{{ $title }}</h1>

    <span class="topbar__total" data-order-total>Pedido {{ \App\Support\Price::format($total) }}</span>
</header>
