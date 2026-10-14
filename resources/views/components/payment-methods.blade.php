{{-- Spec: label + options, gap 12 in both containers. --}}
<div class="payment-methods d-flex flex-column">
    <x-section-label variant="strong" id="payment-label">Metodo de pago</x-section-label>

    <div class="payment-methods__options d-flex flex-column" role="group" aria-labelledby="payment-label">
        @if ($pickup)
            {{-- Pickup is paid at the store: a single option, checked from the start, painted
                 like the others so the screen still says how it's going to be paid. --}}
            <x-payment-option id="payment-cash" value="efectivo" title="Efectivo" icon="cash" note="Pagas al recoger tu pedido en el local" :checked="true" />
        @else
            @if ($transfer)
                <x-payment-option
                    id="payment-transfer"
                    value="transferencia"
                    title="Transferencia bancaria"
                    icon="phone"
                    note="Transferencia bancaria"
                    :checked="$checked === 'bank_transfer'"
                />
            @endif

            {{-- Cash on delivery: hidden while CASH_ON_DELIVERY is off. --}}
            @if ($cashOnDelivery)
                <x-payment-option
                    id="payment-cash"
                    value="efectivo"
                    title="Efectivo"
                    icon="cash"
                    note="Pagas al recibir tu pedido"
                    :checked="$checked === 'efectivo'"
                />
            @endif

            {{-- The only method with a brand: its logo instead of the card icon, with ITS
                 colors (not through <x-icon>, which paints a stroke inheriting the color).
                 The width/height are the file's (mobile); desktop's come from the CSS. --}}
            <x-payment-option
                id="payment-mercadopago"
                value="mercadopago"
                title="Mercado Pago"
                note="Paga con Mercado Pago"
                :checked="$checked === 'mercado_pago'"
            >
                <x-slot:media>
                    <x-inline-svg src="icons/mp-logo.svg" class="payment-option__logo" width="68" height="28" aria-hidden="true" focusable="false" />
                </x-slot:media>
            </x-payment-option>
        @endif
    </div>
</div>
