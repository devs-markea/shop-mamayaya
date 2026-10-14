{{--
    A header without a title: only the back control (64px, 40px touch box). Port of
    src/components/BackHeader.astro.

    `mobileOnly`: hidden from md up, for screens with their own desktop header
    (.navbar-desktop), where the bar's logo already leads back to the menu.
--}}
@props(['backHref' => '/mamayaya', 'mobileOnly' => false])

<header @class(['topbar', 'topbar--compact', 'topbar--mobile' => $mobileOnly])>
    <x-back-button :href="$backHref" />
</header>
