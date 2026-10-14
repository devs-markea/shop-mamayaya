{{--
    The back control of the .topbar headers (TopBar, BackHeader, ScreenHeader): the
    system's 20px icon inside a 24px touch box. Port of src/components/BackButton.astro;
    the whole .topbar block lives in its CSS (resources/css/components/back-button.css).
--}}
@props(['href' => '/mamayaya', 'label' => 'Volver'])

<a class="topbar__back" href="{{ $href }}" aria-label="{{ $label }}">
    <x-icon name="arrow-left" />
</a>
