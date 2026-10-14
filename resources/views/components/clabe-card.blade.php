{{--
    The CLABE again, at hand and without going back: on the acknowledgement it's the only
    data needed to complete the transfer. Port of src/components/ClabeCard.astro.

    `clabe`: the 18 raw digits; grouped only to be painted (App\Support\Clabe).
--}}
@props(['bank' => null, 'clabe'])

<div class="clabe-card">
    {{-- Desktop only: on mobile the card goes after the steps, which already say a
         transfer is due. --}}
    <x-section-label as="h2" class="d-none d-md-block">Datos para Transferencia</x-section-label>

    {{-- The data and its action. The row only exists on desktop (`display: contents`). --}}
    <div class="clabe-card__row">
        <div class="clabe-card__field">
            <span class="clabe-card__label">CLABE{{ $bank ? " {$bank}" : '' }}</span>
            <span class="clabe-card__value">{{ \App\Support\Clabe::format($clabe) }}</span>
        </div>

        {{-- Copied raw, not the grouped text: it's what online banking accepts. --}}
        <x-copy-button :value="$clabe" label="Copiar la CLABE" size="lg" />
    </div>
</div>
