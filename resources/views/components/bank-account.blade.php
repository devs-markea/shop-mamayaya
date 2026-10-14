{{--
    The destination account's card. Port of src/components/BankAccount.astro.

    The three fields come from GET /api/store (with the fallback), and the API publishes
    them all or nothing; here each row is still painted only when it has data. On desktop
    the card gets a heading and the CLABE turns gold: it's the field that's copied.

    `clabe`: the 18 raw digits; grouped only to be painted (App\Support\Clabe).
--}}
@props(['holder' => null, 'bank' => null, 'clabe' => null])

<div class="bank-account">
    {{-- Desktop only: on mobile the screen header ("Pago por transferencia") already says
         what these fields are. --}}
    <x-section-label as="h2" variant="strong" class="d-none d-md-block">Datos de transferencia</x-section-label>

    {{-- The group only exists on desktop (`display: contents` on mobile). --}}
    <div class="bank-account__fields">
        @if ($holder)
            <div class="bank-account__field">
                <x-section-label variant="soft">Titular</x-section-label>
                <span class="bank-account__value">{{ $holder }}</span>
            </div>
        @endif

        @if ($bank)
            <div class="bank-account__field">
                <x-section-label variant="soft">Banco</x-section-label>
                <span class="bank-account__value">{{ $bank }}</span>
            </div>
        @endif

        @if ($clabe)
            <div class="bank-account__field">
                <x-section-label variant="soft">CLABE</x-section-label>

                <div class="bank-account__row">
                    <span class="bank-account__value bank-account__value--clabe">{{ \App\Support\Clabe::format($clabe) }}</span>

                    {{-- The raw CLABE is copied, not the grouped text: it's what gets pasted
                         into online banking. --}}
                    <x-copy-button :value="$clabe" label="Copiar la CLABE" />
                </div>
            </div>
        @endif
    </div>
</div>
