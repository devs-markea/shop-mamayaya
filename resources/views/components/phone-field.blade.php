{{--
    The contact phone: country selector (75) + number. The label belongs to the GROUP (one
    datum split in two controls) and points to the number, where it's typed. On mobile it
    isn't painted but stays in the tree, naming the field for screen readers. Port of
    src/components/PhoneField.astro.
--}}
<div class="field-group">
    <label class="field-label" for="field-phone">Telefono de contacto</label>

    <div class="field-pair d-flex">
        <x-country-select />

        <div class="flex-grow-1">
            <x-text-field name="phone" placeholder="Numero de telefono" type="tel" inputmode="tel" autocomplete="tel-national" required />
        </div>
    </div>
</div>
