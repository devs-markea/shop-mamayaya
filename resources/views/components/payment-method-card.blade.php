{{--
    How the order is paid, once closed: the counterpart of <x-bank-account> in the cash
    flow. Port of src/components/PaymentMethodCard.astro. Not <x-payment-option>: that one
    is a radio to choose, this one a fact that no longer changes.

    `wideNote`: what the note slot says on desktop, where the card is wider and the
    sentence below retires. Without it, `note` serves both widths and the sentence stays.
--}}
@props(['icon', 'title', 'note', 'wideNote' => null, 'description'])

<div class="payment-method">
    <div class="payment-method__body">
        <div class="payment-method__head">
            <span class="payment-method__media">
                <x-icon :name="$icon" />
            </span>

            <span class="payment-method__text">
                <span class="payment-method__title">{{ $title }}</span>

                <span @class(['payment-method__note', 'd-md-none' => $wideNote])>{{ $note }}</span>

                @if ($wideNote)
                    <span class="payment-method__note d-none d-md-block">{{ $wideNote }}</span>
                @endif
            </span>
        </div>

        <p class="payment-method__description">{{ $description }}</p>
    </div>
</div>
