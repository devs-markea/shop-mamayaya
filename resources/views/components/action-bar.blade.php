<div class="action-bar">
    @if ($disabled)
        {{-- <button disabled> and not a dimmed <a>: a link without href is still focusable
             and announced as a destination. `type="button"` so it doesn't submit. --}}
        <button class="{{ $buttonClass }}" type="button" disabled>
            @if ($icon)
                <x-brand-icon :name="$icon" />
            @endif
            <span>{{ $text }}</span>
        </button>
    @elseif ($href)
        <a class="{{ $buttonClass }}" href="{{ $href }}">
            @if ($icon)
                <x-brand-icon :name="$icon" />
            @endif
            <span>{{ $text }}</span>
        </a>
    @else
        <button class="{{ $buttonClass }}" type="submit">
            @if ($icon)
                <x-brand-icon :name="$icon" />
            @endif
            @if ($hasTotal)
                <span data-total data-total-template="{{ $label }}">{{ $text }}</span>
            @else
                <span>{{ $text }}</span>
            @endif
        </button>
    @endif

    @if ($secondary)
        @if (! empty($secondary['href']))
            <a class="{{ $secondaryClass }}" href="{{ $secondary['href'] }}">
                @if (! empty($secondary['icon']))
                    <x-brand-icon :name="$secondary['icon']" />
                @endif
                <span>{{ $secondary['label'] }}</span>
            </a>
        @else
            {{-- Without a destination it stays inert, also inside a form. --}}
            <button class="{{ $secondaryClass }}" type="button">
                @if (! empty($secondary['icon']))
                    <x-brand-icon :name="$secondary['icon']" />
                @endif
                <span>{{ $secondary['label'] }}</span>
            </button>
        @endif
    @endif

    @if ($note)
        <p class="action-bar__note">{{ $note }}</p>
    @endif
</div>
