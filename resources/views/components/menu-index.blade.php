{{--
    The home page index list: one entry per section, with its name and dish count.
    Painted by the desktop sidebar and the mobile category sheet, with the same look and
    logic. An index, not a filter: links that work without JS too. Port of
    src/components/MenuIndex.astro.
--}}
@props(['sections'])

<ul class="menu-index list-unstyled mb-0">
    @foreach ($sections as $index => $section)
        @php($count = count($section['products']))
        <li>
            <a
                @class(['menu-index__link', 'menu-index__link--active' => $index === 0])
                href="#{{ $section['anchor'] }}"
                @if ($index === 0) aria-current="true" @endif
                data-menu-link="{{ $section['anchor'] }}"
            >
                {{-- The title brings back what the ellipsis eats. --}}
                <span class="menu-index__label" title="{{ $section['name'] }}">{{ $section['name'] }}</span>
                <span class="menu-index__count">{{ $count }} <span class="visually-hidden">{{ $count === 1 ? 'platillo' : 'platillos' }}</span></span>
            </a>
        </li>
    @endforeach
</ul>
