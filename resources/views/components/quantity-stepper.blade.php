{{--
    The units counter of a cart line or of a product page option (.stepper). Port of
    src/components/QuantityStepper.astro. `itemName` labels both buttons unambiguously;
    `max` is the ceiling (without it the "+" never stops); `disabled` is a read-only group.
--}}
@props(['quantity', 'itemName', 'min' => 1, 'max' => null, 'disabled' => false])

<div class="stepper" data-stepper>
    <button
        class="stepper__button stepper__button--minus btn-plain"
        type="button"
        aria-label="Quitar una unidad de {{ $itemName }}"
        data-step="-1"
        @disabled($disabled || $quantity <= $min)
    >
        {{-- The spec asks for a 10x1.5 rectangle, not a stroke icon. --}}
        <span class="stepper__minus"></span>
    </button>

    <span class="stepper__value" aria-live="polite" data-quantity>{{ $quantity }}</span>

    <button
        class="stepper__button stepper__button--plus btn-plain"
        type="button"
        aria-label="Agregar una unidad de {{ $itemName }}"
        data-step="1"
        @disabled($disabled || ($max !== null && $quantity >= $max))
    >
        <x-icon name="plus" size="sm" />
    </button>
</div>
