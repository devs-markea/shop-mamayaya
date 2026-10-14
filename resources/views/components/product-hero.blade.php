{{--
    The product page photo, with the back control on top. Port of
    src/components/ProductHero.astro.

    It receives the photo already resolved: the page decides whether this block exists
    (a dish WITHOUT a photo doesn't mount it). The API exposes no size variants, so the
    original is requested and the CSS crops it; 520x480 are the desktop spec's, as
    attributes so the space is reserved before the stylesheet applies. Choosing a variant
    with its own photo swaps it in place (resources/js/scripts/product-hero.ts).
--}}
@props(['image'])

<div class="product-hero" data-product-hero>
    <img class="product-hero__image" data-hero-image src="{{ $image['src'] }}" alt="{{ $image['alt'] }}" width="520" height="480" loading="eager" decoding="async" />

    {{-- Not in the spec: without it the product page has no way out. --}}
    <a class="product-hero__back" href="/mamayaya" aria-label="Volver al menu">
        <x-icon name="chevron-left" />
    </a>
</div>
