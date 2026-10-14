{{-- Radios, like the switch and the chips: the shared name guarantees a single choice. --}}
<div class="tip">
    <x-section-label variant="strong" id="tip-label">Propina</x-section-label>

    <div class="tip__options" role="group" aria-labelledby="tip-label">
        @foreach ($amounts as $amount)
            {{-- The 0's aria-label: "$0" alone says nothing to a screen reader, and it's
                 precisely the option that has to be findable. The spec labels them
                 without decimals ("$0", not "$0.00"). --}}
            <input
                class="btn-check"
                type="radio"
                name="tip"
                id="tip-{{ $amount }}"
                value="{{ $amount }}"
                @checked($amount === $selected)
                autocomplete="off"
                @if ($amount === 0) aria-label="Sin propina" @endif
                data-tip
            />
            <label class="tip__option" for="tip-{{ $amount }}">${{ $amount }}</label>
        @endforeach

        {{-- "Otro": its `value` IS the free amount, so the rest of the flow keeps reading
             the checked radio. Born empty, which resolves as 0. --}}
        <input
            class="btn-check"
            type="radio"
            name="tip"
            id="tip-custom"
            value="{{ $customValue }}"
            @checked($custom)
            autocomplete="off"
            data-tip
            data-tip-custom
        />
        <label class="tip__option" for="tip-custom" data-tip-custom-label>Otro</label>
    </div>

    {{-- The free amount's field, opened and closed by the "Otro" option (a CSS animation
         triggered by the radio itself, not by a class). --}}
    <div class="tip__custom">
        <div class="tip__custom-clip">
            <div class="tip__custom-body">
                {{-- Hidden on mobile, where the placeholder labels it, and painted from md up;
                     it always exists so the field reaches the screen reader named. --}}
                <label class="field-label" for="tip-amount">Otra cantidad</label>

                <div class="tip__amount">
                    <span class="tip__amount-currency" aria-hidden="true">$</span>

                    {{-- `text` with the numeric keyboard, not `type="number"`: only digits are
                         needed, and the script takes care of those. --}}
                    <input
                        class="tip__amount-input"
                        type="text"
                        id="tip-amount"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="{{ $maxDigits }}"
                        placeholder="Otra cantidad"
                        autocomplete="off"
                        value="{{ $customValue }}"
                        data-tip-amount
                    />
                </div>
            </div>
        </div>
    </div>
</div>
