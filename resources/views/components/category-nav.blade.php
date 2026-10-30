{{--
    The MOBILE index: one tab per category, the desktop sidebar in another shape. Each
    tab jumps to its section and the marked one is the section in view; both are moved by
    resources/js/scripts/menu-nav.ts. Port of src/components/CategoryNav.astro.

    No Featured tab: the bar only shows inside a category. It arrives hidden
    (`category-tabs--hidden`), since the page opens at the top. `sheet`: the id of the
    category sheet the right-hand button opens.

    The mobile search lives below the tabs, inside the sliding bar: it appears and hides
    with them, and this is the only place it's painted on mobile.
--}}
@props(['sections', 'sheet' => null])

@php($tabs = array_values(array_filter($sections, fn ($section) => $section['kind'] !== 'featured')))

@if ($tabs !== [])
    <div
        class="category-tabs category-tabs--hidden"
        data-menu-nav="category-tabs__tab--active"
        data-menu-nav-autohide="category-tabs--hidden"
    >
        <div class="category-tabs__bar">
            <nav class="category-tabs__row" aria-label="Categorias del menu">
                <ul class="category-tabs__track list-unstyled mb-0" data-menu-nav-scroll>
                    @foreach ($tabs as $index => $section)
                        <li class="category-tabs__item">
                            <a
                                @class(['category-tabs__tab', 'category-tabs__tab--active' => $index === 0])
                                href="#{{ $section['anchor'] }}"
                                @if ($index === 0) aria-current="true" @endif
                                data-menu-link="{{ $section['anchor'] }}"
                            >{{ $section['name'] }}</a>
                        </li>
                    @endforeach
                </ul>

                @if ($sheet)
                    <button class="category-tabs__menu btn-plain" type="button" aria-label="Ver todas las categorias" aria-haspopup="dialog" data-sheet="{{ $sheet }}">
                        <x-icon name="list" />
                    </button>
                @endif
            </nav>

            <div class="category-tabs__search">
                <x-menu-search :shortcut="false" />
            </div>
        </div>
    </div>
@endif
