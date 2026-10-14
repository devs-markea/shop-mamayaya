{{--
    The closing screens' control ON DESKTOP: it closes the column and takes over from the
    bottom action bar, which isn't painted from md up. Port of
    src/components/ConfirmationCta.astro.

    Not another action: the SAME as the bar (label, icon, destination and notice), only
    shaped for the column. That's why it takes the same props as <x-action-bar>.

    `href`: a link instead of a submit. `variant`: primary (gold) or whatsapp.
    `secondary`: {label, href?, icon?, variant?}, a second action under the main one.
--}}
@props(['label', 'href' => null, 'icon' => null, 'variant' => 'primary', 'note' => null, 'secondary' => null])

@php
    $classFor = fn (string $color) => implode(' ', array_filter([
        'confirmation-page__cta-button',
        'btn',
        'btn-primary',
        $color === 'whatsapp' ? 'confirmation-page__cta-button--whatsapp' : null,
    ]));
@endphp

<div class="confirmation-page__cta">
    @if ($href)
        <a class="{{ $classFor($variant) }}" href="{{ $href }}">
            @if ($icon)
                <x-brand-icon :name="$icon" />
            @endif
            <span>{{ $label }}</span>
        </a>
    @else
        {{-- Without a destination it's inert like the bar's: a submit outside a form
             neither navigates nor reloads. Inside the Mercado Pago retry form, this same
             button is the one that charges. --}}
        <button class="{{ $classFor($variant) }}" type="submit">
            @if ($icon)
                <x-brand-icon :name="$icon" />
            @endif
            <span>{{ $label }}</span>
        </button>
    @endif

    @if ($secondary)
        @if (! empty($secondary['href']))
            <a class="{{ $classFor($secondary['variant'] ?? 'primary') }}" href="{{ $secondary['href'] }}">
                @if (! empty($secondary['icon']))
                    <x-brand-icon :name="$secondary['icon']" />
                @endif
                <span>{{ $secondary['label'] }}</span>
            </a>
        @else
            {{-- Inert, and `type="button"` keeps it so inside the retry form too. --}}
            <button class="{{ $classFor($secondary['variant'] ?? 'primary') }}" type="button">
                @if (! empty($secondary['icon']))
                    <x-brand-icon :name="$secondary['icon']" />
                @endif
                <span>{{ $secondary['label'] }}</span>
            </button>
        @endif
    @endif

    @if ($note)
        <p class="confirmation-page__cta-note">{{ $note }}</p>
    @endif
</div>
