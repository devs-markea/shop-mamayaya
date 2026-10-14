{{--
    The home page search. DESKTOP ONLY: in the order bar's row, left of the switch. It
    searches the dishes already on the page (no API call); resources/js/scripts/menu-search.ts
    does the rest. Port of src/components/MenuSearch.astro.
--}}
<label class="menu-search">
    <x-icon name="search" class="menu-search__icon" />

    <input
        class="menu-search__input"
        type="search"
        placeholder="Buscar en el menú"
        aria-label="Buscar en el menú"
        aria-keyshortcuts="Meta+K Control+K"
        autocomplete="off"
        enterkeyhint="search"
        data-menu-search
    />
</label>
