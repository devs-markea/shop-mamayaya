{{--
    The home page's side column. DESKTOP ONLY: the store on top and the section index
    below (the same list as the mobile sheet). Port of src/components/MenuSidebar.astro.

    `heading`: `home.panel.presentation` of GET /api/store or, without it, the store name.
--}}
@props(['heading' => '', 'sections'])

<aside class="menu-sidebar" aria-label="Menu" data-menu-nav="menu-index__link--active">
    <div class="menu-sidebar__inner" data-menu-nav-scroll>
        @if ($heading)
            <p class="menu-sidebar__name">{{ $heading }}</p>
        @endif

        @if ($sections !== [])
            <hr class="menu-sidebar__rule" />

            <nav aria-label="Categorias del menu">
                <x-menu-index :sections="$sections" />
            </nav>
        @endif
    </div>
</aside>
