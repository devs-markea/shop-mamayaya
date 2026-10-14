{{--
    Copy to clipboard. Port of src/components/CopyButton.astro. The behavior comes from
    resources/js/scripts/copy-button.ts, which delegates on document: the page using this
    component has to import it.

    Both icons are in the markup and the script only toggles the modifier, so JavaScript
    doesn't need to know the paths.

    `value`: what goes to the clipboard, as is. `label`: the accessible name, saying WHAT
    is copied. `size`: lg is the 36px box instead of 32.
--}}
@props(['value', 'label', 'size' => 'md'])

<button @class(['copy-button', 'btn-plain', 'copy-button--lg' => $size === 'lg']) type="button" aria-label="{{ $label }}" data-copy="{{ $value }}">
    <x-icon name="copy" size="sm" class="copy-button__icon" />
    <x-icon name="check" size="sm" class="copy-button__done" />
</button>

{{-- The confirmation for screen readers: the icon swap isn't announced. --}}
<p class="visually-hidden" role="status" data-copy-status></p>
