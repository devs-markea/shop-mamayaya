{{--
    The order's general comments. `value`: what was already written, when coming back
    from checkout. The field grows as you type (data-auto-grow, resources/js/scripts/
    auto-grow.ts) and is saved to the draft on change (order-summary.ts). Port of
    src/components/OrderComments.astro.
--}}
@props(['value' => ''])

<div class="order-comments">
    <x-section-label as="label" for="order-comments">Comentarios generales</x-section-label>

    {{-- .form-control brings padding 16, radius 12, border 1 and the placeholder. --}}
    <textarea
        class="order-comments__field form-control"
        id="order-comments"
        name="comments"
        rows="3"
        maxlength="1000"
        placeholder="Instrucciones adicionales, alergias..."
        data-auto-grow>{{ $value }}</textarea>
</div>
