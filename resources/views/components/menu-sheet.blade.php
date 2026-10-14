{{--
    The MOBILE category sheet: the whole index, with the store and its shift on top. The
    tabs' right-hand button opens it. A system sheet underneath (.sheet on a native
    <dialog>), with its own design and a handle that closes it by dragging down
    (resources/js/scripts/sheet-drag.ts). Port of src/components/MenuSheet.astro.
--}}
@props(['id', 'heading' => '', 'shift' => null, 'sections'])

<dialog class="sheet menu-sheet" id="{{ $id }}" aria-labelledby="{{ $id }}-title">
    {{-- The handle. The whole 24px strip answers the finger, not just the grip.
         Decorative for screen readers: it closes with the X or Escape. --}}
    <div class="menu-sheet__handle" aria-hidden="true" data-sheet-drag>
        <span class="menu-sheet__grip"></span>
    </div>

    <div class="menu-sheet__header">
        <div class="menu-sheet__top">
            <h2 class="menu-sheet__title" id="{{ $id }}-title">{{ $heading ?: 'Categorias' }}</h2>

            <button class="menu-sheet__close btn-plain" type="button" aria-label="Cerrar" data-sheet-close>
                <x-icon name="close" class="menu-sheet__close-icon" />
            </button>
        </div>

        @if ($shift)
            <p class="menu-sheet__shift">{{ $shift }}</p>
        @endif
    </div>

    <nav class="menu-sheet__nav" aria-label="Categorias del menu" data-menu-nav="menu-index__link--active" data-menu-nav-scroll>
        <x-menu-index :sections="$sections" />
    </nav>
</dialog>
