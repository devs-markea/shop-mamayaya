<div
    class="option-group"
    role="group"
    aria-labelledby="{{ $labelId }}"
    data-option-group
    data-group-id="{{ $group['id'] }}"
    data-kind="{{ $group['kind'] }}"
    data-control="{{ $group['control'] }}"
    data-label="{{ $group['label'] }}"
    data-min="{{ $group['min'] }}"
    data-max="{{ $group['max'] ?? '' }}"
    data-max-per-option="{{ $group['maxPerOption'] ?? '' }}"
    @if ($inert) data-inert @endif
>
    <div class="option-group__header">
        <x-section-label :id="$labelId">{{ $group['label'] }}</x-section-label>
        <span @class(['tag', 'badge', 'tag--required' => $group['required'], 'tag--optional' => ! $group['required']])>{{ $group['required'] ? 'Obligatorio' : 'Opcional' }}</span>
    </div>

    @if ($showHint)
        <p class="option-group__hint">{{ $hint }}</p>
    @endif

    <div class="option-group__list">
        @if ($group['control'] === 'quantity')
            @foreach ($group['choices'] as $choice)
                {{-- The counter's ceiling: maxPerOption bounded by max (99 without either).
                     What the others spent is the script's job: at max it disables every "+". --}}
                <div class="option option--quantity" data-choice data-option-id="{{ $choice['id'] }}" data-price="{{ $effectivePrice($choice) }}">
                    <span class="option__label"><span class="option__name">{{ $choiceName($choice['label']) }}</span>{{ $choicePrice($choice['price']) }}</span>

                    {{-- Filled by the script between the second unit and the cap. --}}
                    <span class="option__amount" data-option-amount hidden></span>

                    <x-quantity-stepper :quantity="0" :item-name="$choice['label']" :min="0" :max="$unitCap" :disabled="$inert" />
                </div>
            @endforeach
        @else
            @foreach ($group['choices'] as $choice)
                @php($id = "{$group['id']}-{$choice['id']}")
                <label class="option" for="{{ $id }}">
                    {{-- The variant's own photo travels on its radio; product-hero.ts puts it
                         in the header when chosen. --}}
                    <input
                        class="option__input form-check-input"
                        type="{{ $inputType }}"
                        name="{{ $group['name'] }}"
                        id="{{ $id }}"
                        value="{{ $choice['id'] }}"
                        @checked($choice['checked'])
                        @disabled($inert)
                        data-choice
                        data-option-id="{{ $choice['id'] }}"
                        data-price="{{ $effectivePrice($choice) }}"
                        @if ($choice['image']) data-image-src="{{ $choice['image']['src'] }}" data-image-alt="{{ $choice['image']['alt'] }}" @endif
                    />

                    {{-- A discounted variant splits its label in three: name, struck-through
                         list price and final price. Everything else is a single string. --}}
                    @if ($choice['finalPrice'] === null)
                        <span class="option__label"><span class="option__name">{{ $choiceName($choice['label']) }}</span>{{ $choicePrice($choice['price']) }}</span>
                    @else
                        <span class="option__label"><span class="option__name">{{ $choiceName($choice['label']) }}</span> — <s class="price-was">{{ \App\Support\Price::format($choice['price']) }}</s> <span class="option__price-final">{{ \App\Support\Price::format($choice['finalPrice']) }}</span></span>
                    @endif
                </label>
            @endforeach
        @endif
    </div>

    {{-- ALWAYS born empty: nothing known on the server justifies a notice before the
         buyer touches the group. order-form.ts fills it (missing minimums, then the 422,
         which the API names per group in `options.{personalizationId}`). --}}
    <p class="option-group__error" id="{{ $errorId }}" role="alert" hidden data-group-error></p>
</div>
