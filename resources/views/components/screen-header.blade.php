{{--
    A screen header: back control + centered title, 124px tall, for the inner screens
    (delivery data, payment...). Port of src/components/ScreenHeader.astro; the .topbar
    block lives in back-button.css. For the 48px bar with the total, <x-top-bar>.
--}}
@props(['title', 'backHref' => '/mamayaya', 'mobileOnly' => false])

<header @class(['topbar', 'topbar--tall', 'topbar--mobile' => $mobileOnly])>
    <x-back-button :href="$backHref" />

    <h1 class="topbar__title topbar__title--centered">{{ $title }}</h1>

    {{-- Counterweight of the back control: with space-between, the middle element is only
         centered if the first and the last measure the same. --}}
    <span class="topbar__spacer" aria-hidden="true"></span>
</header>
