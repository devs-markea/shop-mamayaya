{{--
    UNITS OF THE DISH, not of an option: how many times THIS configuration goes into the
    cart, next to "Agregar" on the desktop product page. Port of
    src/components/ProductCounter.astro.

    Its hooks (`data-quantity-step`) are not the option counters' (`data-step` inside a
    `[data-choice]`): mixing them would raise an option when asking for another unit. Only
    visible on desktop; on mobile the 1 here is what travels. The floor is 1; the ceiling
    is set by order-form.ts, which moves the number.
--}}
@props(['itemName', 'quantity' => 1])

<div class="product-counter" data-quantity-counter>
    <button
        class="product-counter__button product-counter__button--minus btn-plain"
        type="button"
        aria-label="Quitar una unidad de {{ $itemName }}"
        data-quantity-step="-1"
        @disabled($quantity <= 1)
    >
        {{-- The spec asks for a 12x2 rectangle, not a stroke icon. --}}
        <span class="product-counter__minus"></span>
    </button>

    <span class="product-counter__value" aria-live="polite" data-quantity-value>{{ $quantity }}</span>

    <button
        class="product-counter__button product-counter__button--plus btn-plain"
        type="button"
        aria-label="Agregar una unidad de {{ $itemName }}"
        data-quantity-step="1"
    >
        <x-icon name="plus" size="sm" class="product-counter__plus" />
    </button>
</div>
