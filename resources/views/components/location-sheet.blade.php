{{--
    The store location, behind the pin icon. Port of src/components/LocationSheet.astro.

    The text is the business's message or, without one, the address: never both. The
    message is HTML rewritten by the backend (only <strong> and <br>, the rest escaped),
    so it's injected as is; escaping it again would double it. The text and the Maps link
    work separately: with only the link, a hint replaces the text.
--}}
@props(['message' => '', 'address' => '', 'mapsUrl' => ''])

<x-sheet id="sheet-location" title="Ubicación">
    @if ($message !== '')
        <p class="location-sheet__message">{!! $message !!}</p>
    @elseif ($address !== '')
        <p class="location-sheet__address">{{ $address }}</p>
    @else
        <p class="location-sheet__hint">Abre el mapa para ver donde estamos.</p>
    @endif

    @if ($mapsUrl !== '')
        <a class="location-sheet__link btn btn-primary" href="{{ $mapsUrl }}" target="_blank" rel="noopener">
            <x-icon name="map-pin" size="sm" />
            <span>Abrir en Google Maps</span>
        </a>
    @endif
</x-sheet>
