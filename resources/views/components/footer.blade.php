<footer class="footer">
    <div class="footer__inner">
        <div class="footer__top">
            <div class="footer__brand">
                <a class="footer__brand-link" href="/mamayaya">
                    <x-brand-logo class="footer__logo" />
                </a>
            </div>

            <div class="footer__columns">
                @if ($hasAbout)
                    <div class="footer__group">
                        <h2 class="footer__heading">{{ $aboutTitle }}</h2>

                        <ul class="footer__list list-unstyled">
                            @if ($hasLocation)
                                <li>
                                    <button class="footer__item btn-plain" type="button" aria-haspopup="dialog" data-sheet="sheet-location">
                                        <x-icon name="map-pin" />
                                        <span>{{ $locationLabel }}</span>
                                    </button>
                                </li>
                            @endif

                            @if ($hasSchedule)
                                <li>
                                    <button class="footer__item btn-plain" type="button" @if ($clockName) aria-label="{{ $clockName }}" @endif aria-haspopup="dialog" data-sheet="sheet-schedule">
                                        <x-icon name="clock" />
                                        <span>{{ $shift }}</span>
                                    </button>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endif

                @if ($socials !== [])
                    <div class="footer__group">
                        <h2 class="footer__heading">{{ $followTitle }}</h2>

                        <ul class="footer__list list-unstyled">
                            @foreach ($socials as $social)
                                <li>
                                    <a class="footer__item" href="{{ $social['url'] }}" target="_blank" rel="noopener">
                                        <x-brand-icon :name="$social['network']" />
                                        <span>{{ $social['text'] }}@unless ($social['named'])<span class="visually-hidden">, {{ $social['label'] }}</span>@endunless</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- The title is the question; the number is named by its own hidden label.
                     `novalidate` because the script writes the notices with the API's texts.
                     30 is the API's cap. --}}
                <form class="footer__promo" data-promo-form novalidate>
                    <div class="footer__promo-body">
                        <h2 class="footer__promo-heading">{{ $promoTitle }}</h2>

                        <div class="footer__promo-field">
                            <label class="visually-hidden" for="footer-promo-phone">{{ $promoPlaceholder }}</label>
                            <input
                                class="footer__promo-input"
                                id="footer-promo-phone"
                                name="phone"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                placeholder="{{ $promoPlaceholder }}"
                                maxlength="30"
                                aria-describedby="footer-promo-status"
                                required
                            />
                        </div>
                    </div>

                    <button class="footer__promo-button btn-plain" type="submit">{{ $promoButton }}</button>

                    {{-- The slot exists from the start, empty and hidden: a notice arriving in
                         a freshly inserted region isn't always announced. --}}
                    <p class="footer__promo-status" id="footer-promo-status" role="status" hidden data-promo-status></p>
                </form>
            </div>
        </div>

        <hr class="footer__divider" />

        @if ($copyright !== '')
            <p class="footer__copyright footer__copyright--rich">{!! $copyright !!}</p>
        @elseif (($store['name'] ?? '') !== '')
            <p class="footer__copyright">© {{ $year }} <strong>{{ $store['name'] }}</strong>. Todos los derechos reservados.</p>
        @else
            <p class="footer__copyright">© {{ $year }}. Todos los derechos reservados.</p>
        @endif
    </div>
</footer>
