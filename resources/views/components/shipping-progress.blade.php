{{--
    The progress bar towards free shipping. The screen decides whether it's mounted (not
    with an empty order). Reaching the threshold changes the text, not the color; the copy
    lives in App\Support\Shipping / resources/js/lib/shipping.ts, because the script
    rewrites it on every quantity change. Port of src/components/ShippingProgress.astro.
--}}
@props(['remaining', 'progress'])

<section class="shipping-progress" aria-label="Progreso hacia el envio gratis">
    <p class="shipping-progress__label" data-shipping-label>{{ \App\Support\Shipping::freeShippingLabel($remaining) }}</p>

    {{-- Bootstrap's .progress is the track and .progress-bar the fill. --}}
    <div
        class="progress"
        role="progressbar"
        aria-label="Avance del pedido hacia el envio gratis"
        aria-valuenow="{{ $progress }}"
        aria-valuemin="0"
        aria-valuemax="100"
        data-shipping-progress
    >
        <div class="progress-bar" style="width: {{ $progress }}%"></div>
    </div>
</section>
