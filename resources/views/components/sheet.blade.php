{{--
    A modal sheet from the top bar (schedule, location). A native <dialog>, not
    Bootstrap's modal: it already brings the backdrop, Escape, focus trapping and an
    inert page. resources/js/scripts/sheet.ts opens and closes it. Port of
    src/components/Sheet.astro.

    `wide`: wider on desktop, for sheets you work in (the map). `persistent`: clicking
    outside doesn't close it, for sheets where something can be lost.
--}}
@props(['id', 'title', 'wide' => false, 'persistent' => false])

<dialog
    @class(['sheet', 'sheet--wide' => $wide])
    id="{{ $id }}"
    aria-labelledby="{{ $id }}-title"
    @if ($persistent) data-sheet-persistent @endif
>
    <div class="sheet__header">
        <h2 class="sheet__title" id="{{ $id }}-title">{{ $title }}</h2>

        {{-- Our own close button and not Bootstrap's .btn-close (a background image with
             its own weight and black): the icon follows the system. --}}
        <button class="sheet__close btn-plain" type="button" aria-label="Cerrar" data-sheet-close>
            <x-icon name="close" size="sm" />
        </button>
    </div>

    <div class="sheet__body">
        {{ $slot }}
    </div>
</dialog>
