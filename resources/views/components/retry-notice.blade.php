{{--
    The "something failed and you can try again" notice. Port of
    src/components/RetryNotice.astro; its countdown is resources/js/components/retry-notice.ts.

    `retry`, decided by whoever mounts it:
      auto    transient failure: countdown, reload on its own, and a manual link
      manual  a 429: only the link (reloading against a per-minute cap wastes a token)
      none    a 404: neither (insisting won't make the dish appear)

    The countdown is born `hidden` and the script reveals it: with JS down, the page
    must not promise a retry that will never happen. The manual way out is a real <a> to
    this same address, so it works without JS too.
--}}
@props(['body', 'code' => null, 'retry', 'center' => false])

<div @class(['retry-notice', 'retry-notice--center' => $center]) data-retry-mode="{{ $retry }}">
    {{-- role="status" only HERE: the countdown stays out so screen readers don't repeat
         "3", "2", "1" on top of the notice. --}}
    <p class="retry-notice__body" role="status">{{ $body }}</p>

    @if ($retry === 'auto')
        <p class="retry-notice__countdown" data-retry-countdown hidden>
            {{-- The 3 is a placeholder: the script computes the real wait. --}}
            <span aria-hidden="true">Reintentando en <span data-retry-count>3</span>…</span>
            <span class="visually-hidden">La pagina se actualizara sola en unos segundos.</span>
        </p>
    @endif

    {{-- OUTSIDE the status region: a screen reader user needs to hear what happened, not
         "ese efe guion eme doscientos dos" first. --}}
    @if ($code)
        <p class="retry-notice__code">{{ \App\Support\ErrorCodes::label($code) }}</p>
    @endif

    @if ($retry !== 'none')
        <a class="retry-notice__action" href="{{ request()->getRequestUri() }}" data-retry-action>Reintentar ahora</a>
    @endif
</div>
