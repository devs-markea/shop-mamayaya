{{--
    The delivery location: label + gold button + hint, the inline map and the sheet with
    the device-position fallback. Port of src/components/LocationPicker.astro.

    The chosen point is kept in a hidden input, the API's `customer.locationUrl` (a Google
    Maps link). REQUIRED for delivery and it doesn't replace the written address: the
    written one is what the courier reads; the point is what lets the backend measure the
    distance and quote shipping.

    TWO ways to the map, depending on Google's answer:
      with map     the <x-location-map> panel is painted here, in the button's place
      without map  (no key, rejected key, no network) the panel stays hidden and this
                   button opens the sheet below, whose fallback is the device position
    Both are served; resources/js/lib/location-map.ts decides, revealing the panel and
    removing the pieces marked `data-location-fallback`.

    `mapsKey`: Google Maps BROWSER key (restricted by HTTP referrer, it's visible anyway).
--}}
@props(['mapsKey' => null])

<div class="location" data-location>
    <x-section-label as="h2" class="d-md-none" id="location-label">Ubicacion</x-section-label>

    <button class="location__button btn-plain" type="button" aria-describedby="location-label" aria-haspopup="dialog" data-share-location data-location-fallback>
        <x-icon name="map-pin" />
        <span>Elegir ubicacion en el mapa</span>
    </button>

    {{-- The inline map, which replaces the button when Google answers. Served hidden. --}}
    <x-location-map :maps-key="$mapsKey" />

    <input type="hidden" name="locationUrl" data-location-url />

    {{-- The chosen location. The text itself links to the point in Google Maps. --}}
    <p class="location__selected" hidden data-location-selected>
        <x-icon name="map-pin" size="sm" />

        <a class="location__value" href="#" target="_blank" rel="noopener" data-location-link>
            <span data-location-label></span>
        </a>

        {{-- With the panel on, its red button does this (and unlocks the map). --}}
        <button class="location__clear btn-plain" type="button" aria-label="Quitar la ubicacion" data-location-clear data-location-fallback>Quitar</button>
    </p>

    {{-- The button's hint. The panel carries its own. --}}
    <p class="location__hint d-md-none" data-location-fallback>Mueve el mapa hasta tu domicilio</p>
</div>

{{-- The map sheet. Outside .location so it doesn't inherit its column. --}}
<x-sheet id="location-input" title="Indicar tu ubicacion" wide persistent>
    {{-- The map and its two controls. The key travels in a data-*; without it the block
         removes itself and the fallback below remains. --}}
    <div class="map-picker" data-map-picker @if ($mapsKey) data-maps-key="{{ $mapsKey }}" @endif>
        <p class="sheet__text">Mueve el mapa hasta tu domicilio. El pin marca el punto exacto al que llega el repartidor.</p>

        {{-- Google's search box goes here when the map loads. --}}
        <div class="place-search" hidden data-map-search></div>

        <div class="map-picker__stage" data-map-stage>
            <div class="map-picker__canvas" data-map-canvas></div>

            {{-- The pin, fixed in the center: the map moves underneath it. --}}
            <span class="map-picker__pin" aria-hidden="true">
                <x-icon name="map-pin" />
            </span>

            <button class="map-picker__locate btn-plain" type="button" aria-label="Centrar el mapa en mi ubicacion" data-map-locate>
                <x-icon name="crosshair" size="sm" />
            </button>

            {{-- While the SDK loads, over the map's room so the sheet doesn't jump. --}}
            <p class="map-picker__status" hidden data-map-status></p>
        </div>

        {{-- The pin's address is NOT labelled here, on purpose: the map confirms the
             place, and the written address appears on accepting, resolved by the backend
             when quoting (reading it here cost a Google query on EVERY map stop). --}}
    </div>

    {{-- Accept. If the map doesn't load, the script removes it with the map: it would
         confirm the center of Cancún as someone's home. --}}
    <button class="btn btn-primary" type="button" data-map-apply>Usar esta ubicacion</button>

    {{-- The fallback, only when the map can't be used. It asks the device for its
         position, with an accuracy filter (the point is accepted without seeing it). --}}
    <div class="map-picker__offline" hidden data-map-offline>
        <p class="sheet__text">No pudimos cargar el mapa. Usa la ubicacion de tu dispositivo para indicar donde entregamos: tu pedido necesita un punto en el mapa para poder continuar.</p>

        <button class="btn btn-primary" type="button" data-map-geolocate>
            <x-icon name="crosshair" size="sm" />
            <span>Usar mi ubicacion actual</span>
        </button>

        <x-form-error mark="data-map-offline-error" />
    </div>
</x-sheet>
