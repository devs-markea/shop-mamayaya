{{--
    The written address: neighborhood, street and number (required for delivery), cross
    streets and references (optional). On desktop the first three share a line and the
    optional ones carry their label above; .field-row and .field-group don't exist on
    mobile. Port of src/components/AddressFields.astro.

    `required`: declared by the server with the draft's mode, so a submit before the script
    starts doesn't ask for hidden fields; the switch syncs it afterwards.
--}}
@props(['required' => true])

<div class="field-stack d-flex flex-column">
    {{-- The three required ones. On desktop, one line of three at 12. --}}
    <div class="field-row">
        <x-text-field name="neighborhood" label="Colonia" placeholder="Colonia" autocomplete="address-level3" :required="$required" />
        <x-text-field name="street" label="Calle" placeholder="Calle" autocomplete="address-line1" :required="$required" />
        <x-text-field name="number" label="Numero exterior" placeholder="Numero exterior" autocomplete="address-line2" :required="$required" />
    </div>

    {{-- The two optional ones. The group writes the label: on desktop it goes above the
         field, not inside it; the `for` points to the id the field builds. --}}
    <div class="field-group">
        <label class="field-label" for="field-cross_streets">Entre calles (opcional)</label>
        <x-text-field name="cross_streets" placeholder="Entre calles (opcional)" />
    </div>

    <div class="field-group">
        <label class="field-label field-label--soft" for="field-references">Referencias adicionales (opcional)</label>

        <textarea class="field-note form-control" id="field-references" name="references" placeholder="Referencias (opcional)"></textarea>
    </div>
</div>
