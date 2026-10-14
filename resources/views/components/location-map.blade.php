{{--
    The inline map of /mamayaya/datos: the panel that replaces the button that opened the
    sheet. A map with the pin fixed in the center: move the map to the address and
    confirm. Port of src/components/LocationMap.astro.

    SERVED HIDDEN: whether there's a map is only known in the browser, when asking Google
    for the SDK. resources/js/lib/location-map.ts reveals it once painted; if Google
    doesn't answer, the panel stays hidden and the picker's button comes back.
--}}
@props(['mapsKey' => null])

<section class="location-map" hidden data-location-map @if ($mapsKey) data-maps-key="{{ $mapsKey }}" @endif>
    {{-- The header. The label tells the state, and so it's announced: without seeing the
         map there's no other way to know the point was saved. --}}
    <div class="location-map__head d-flex align-items-center justify-content-between">
        <x-section-label as="p" class="location-map__label" aria-live="polite" data-location-map-label="">Mueve el mapa hasta tu domicilio</x-section-label>

        {{-- Born disabled: until the map is painted, all there is under the pin is the
             center of Cancún. --}}
        <button class="location-map__confirm btn btn-success d-inline-flex align-items-center gap-2" type="button" disabled data-location-map-confirm>
            <x-icon name="check" size="sm" />
            <span>Confirmar ubicacion</span>
        </button>

        {{-- Its replacement once there's a point: discards it and unlocks the map. --}}
        <button class="location-map__cancel btn btn-danger d-inline-flex align-items-center gap-2" type="button" hidden data-location-map-cancel>
            <x-icon name="close" size="sm" />
            <span>Cambiar ubicacion</span>
        </button>
    </div>

    {{-- Google's search box, inserted when the map loads. Hidden until then. --}}
    <div class="place-search" hidden data-location-map-search></div>

    <div class="location-map__stage">
        <div class="location-map__canvas" data-location-map-canvas></div>

        {{-- The confirmed veil, toggled by CSS from the block's state. --}}
        <div class="location-map__lock" aria-hidden="true"></div>

        {{-- The pin, fixed in the center: the map moves underneath, so it takes no pointer. --}}
        <span class="location-map__pin" aria-hidden="true">
            <x-icon name="map-pin" />
        </span>

        <button class="location-map__locate btn-plain" type="button" aria-label="Centrar el mapa en mi ubicacion" data-location-map-locate>
            <x-icon name="crosshair" size="sm" />
        </button>

        {{-- While the SDK loads. It fills the map's room so the form doesn't jump. --}}
        <p class="location-map__status" data-location-map-status>Cargando el mapa…</p>
    </div>
</section>
