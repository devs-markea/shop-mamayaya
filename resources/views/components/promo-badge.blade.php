{{--
    The promotion badge: "15%", "$50", "2x1". The label is composed by the backend; the
    promotion's name goes to the title, since the badge has to fit the corner of a photo.
    Port of src/components/PromoBadge.astro.
--}}
@props(['label', 'name' => null])

<span {{ $attributes->class(['promo-badge']) }} @if ($name) title="{{ $name }}" @endif>
    <x-icon name="tag" size="sm" />
    <span class="promo-badge__label">{{ $label }}</span>
</span>
