<header class="navbar">
    <div class="navbar__inner">
        <a class="navbar__brand" href="/mamayaya">
            <x-brand-logo class="navbar__logo" />
        </a>

        <div class="navbar__actions">
            @if ($hasSchedule)
                <button class="navbar__action btn-plain" type="button" aria-label="Horario de la tienda" aria-haspopup="dialog" data-sheet="sheet-schedule">
                    <x-icon name="clock" class="navbar__icon" />
                </button>
            @endif

            @if ($hasLocation)
                <button class="navbar__action btn-plain" type="button" aria-label="Ubicacion de la tienda" aria-haspopup="dialog" data-sheet="sheet-location">
                    <x-icon name="map-pin" class="navbar__icon" />
                </button>
            @endif

            {{-- Share isn't painted: it waits for the real domain (`publicUrl`). --}}
        </div>
    </div>
</header>

{{-- Both conditions travel to the desktop bar, where they decide the same thing: this
     header mounts the sheets, so no bar can offer a button for a sheet that isn't there. --}}
<x-navbar-desktop
    :has-location="$hasLocation"
    :has-schedule="$hasSchedule"
    :shift="$shift"
    :estimate="$store['delivery']['estimate'] ?? ''"
    :pickup="$store['pickup']['estimate'] ?? ''"
    :selected="$selected"
    :cart-total="$cartTotal"
    :cart-count="$cartCount"
/>

@if ($hasSchedule)
    <x-schedule-sheet :schedule="$schedule" />
@endif

@if ($hasLocation)
    <x-location-sheet
        :message="$store['location']['message'] ?? ''"
        :address="$store['location']['address'] ?? ''"
        :maps-url="$store['location']['mapsUrl'] ?? ''"
    />
@endif
