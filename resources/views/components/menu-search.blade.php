{{--
    The home page search. On desktop it sits in the order bar's row, left of the switch;
    on mobile, below the category tabs. It searches the dishes already on the page (no
    API call); resources/js/scripts/menu-search.ts does the rest and keeps both fields in
    step. Port of src/components/MenuSearch.astro.

    `shortcut`: whether ⌘K / Ctrl+K reaches this field. Only the desktop one has it.
--}}
@props(['shortcut' => true])

<label class="menu-search">
    <x-icon name="search" class="menu-search__icon" />

    <input
        class="menu-search__input"
        type="search"
        placeholder="Buscar en el menú"
        aria-label="Buscar en el menú"
        @if ($shortcut) aria-keyshortcuts="Meta+K Control+K" @endif
        autocomplete="off"
        enterkeyhint="search"
        data-menu-search
    />
</label>
