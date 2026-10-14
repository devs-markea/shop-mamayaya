{{--
    "Something is missing before continuing", or the API rejected the order. ALWAYS born
    empty and hidden: each screen's script writes the text and reveals it, finding it by
    the `data-*` mark given here. The slot exists from the start because a notice arriving
    in a freshly inserted role="alert" isn't always announced. Port of
    src/components/FormError.astro.
--}}
@props(['mark'])

<p class="form-error" role="alert" hidden {{ $mark }}></p>
