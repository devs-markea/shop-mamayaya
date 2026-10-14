<section class="hero" aria-label="Bienvenida">
    <div @class(['hero__panel', 'hero__panel--bare' => ! $hasText]) @if ($photoStyle) style="{{ $photoStyle }}" @endif>
        @if ($hasText || $statusLabel)
            <div class="hero__text">
                @if ($statusLabel)
                    <span @class(['hero__status', 'hero__status--closed' => $status !== 'open'])>{{ $statusLabel }}</span>
                @endif

                @if ($title !== '')
                    <h1 class="hero__title">{{ $title }}</h1>
                @endif

                @if ($description !== '')
                    <p class="hero__subtitle">{{ $description }}</p>
                @endif
            </div>
        @endif
    </div>
</section>
