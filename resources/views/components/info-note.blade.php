{{--
    A margin note: soft gold with the info icon. Not an error nor a state; context the
    buyer is glad to have right there. Port of src/components/InfoNote.astro.

    `mark` / `markText`: the data-* hooks of the container and of the paragraph, for the
    script that reveals it and rewrites its text. `hidden` keeps it in the markup.
--}}
@props(['hidden' => false, 'mark' => null, 'markText' => null])

<div class="info-note" @if ($hidden) hidden @endif @if ($mark) {{ $mark }} @endif>
    <x-icon name="info" size="sm" />
    <p class="info-note__text" @if ($markText) {{ $markText }} @endif>{{ $slot }}</p>
</div>
