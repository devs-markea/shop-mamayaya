{{--
    The legal notice before confirming. Port of src/components/LegalNote.astro.

    "Terminos y condiciones" is a link only when a URL is configured (`legal.termsUrl`).
    Without it, it's plain text: a link leading nowhere is worse than none, and here the
    buyer is told they accept them.
--}}
@props(['termsUrl' => null])

<p class="legal-note">
    Al continuar aceptas nuestros
    @if ($termsUrl)
        <a class="legal-note__link" href="{{ $termsUrl }}" target="_blank" rel="noopener">Terminos y condiciones</a>
    @else
        Terminos y condiciones
    @endif
    de servicio.
</p>
