{{--
    A payment method to choose: a radio plus its card. Port of
    src/components/PaymentOption.astro. Reusable: the icon and the texts come as props,
    and the radio carries the active state.

    The `media` slot is for methods with a brand (Mercado Pago), whose logo is a file
    with its own colors. Filling it also changes the box (a logo is landscape and doesn't
    fit the 40px square), so it's deduced from the slot instead of asking for one more
    prop. Without it, the `icon` rules.
--}}
@props(['id', 'value', 'title', 'icon' => null, 'note' => null, 'tag' => null, 'checked' => false, 'disabled' => false, 'media' => null])

<input
    class="btn-check"
    type="radio"
    name="payment_method"
    id="{{ $id }}"
    value="{{ $value }}"
    @checked($checked)
    @disabled($disabled)
    autocomplete="off"
/>

<label @class(['payment-option', 'payment-option--disabled' => $disabled]) for="{{ $id }}">
    <span @class(['payment-option__media', 'payment-option__media--logo' => $media !== null])>
        @if ($media !== null)
            {{ $media }}
        @elseif ($icon)
            <x-icon :name="$icon" />
        @endif
    </span>

    <span class="payment-option__body">
        <span class="payment-option__title">{{ $title }}</span>

        @if ($note)
            <span class="payment-option__note">{{ $note }}</span>
        @endif

        @if ($tag)
            <span>
                <span class="tag tag--optional badge">{{ $tag }}</span>
            </span>
        @endif
    </span>
</label>
