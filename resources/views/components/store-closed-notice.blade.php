<aside class="store-closed" aria-label="Tienda cerrada">
    <div class="store-closed__card" role="status">
        <p class="store-closed__title">
            <x-icon name="clock" size="sm" />
            <span>{{ $title }}</span>
        </p>

        <p class="store-closed__text">Los pedidos están deshabilitados. Solo podrás ver el menú.</p>

        @if ($opening)
            <p class="store-closed__opening">{{ $opening }}</p>
        @endif

        @if ($hasSchedule)
            <button class="store-closed__action btn-plain" type="button" aria-haspopup="dialog" data-sheet="sheet-schedule">Ver horarios</button>
        @endif
    </div>
</aside>
