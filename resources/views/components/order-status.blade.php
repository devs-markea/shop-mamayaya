{{--
    The close of a result screen: seal, title and notice, all centered. Port of
    src/components/OrderStatus.astro. Shared by the three acknowledgements of
    /mamayaya/recibido, which only differ in the seal, the copy and the folio.

    `state`: success is the green circle with the check (order confirmed); pending the
    gold one with the clock (waiting). `folio`: without the "#", only when it's labelled.
--}}
@props(['title', 'note', 'folio' => null, 'state' => 'success'])

<div class="order-status">
    <span @class(['order-status__seal', 'order-status__seal--pending' => $state === 'pending'])>
        <x-icon :name="$state === 'pending' ? 'clock' : 'check'" size="lg" />
    </span>

    <div class="order-status__titles">
        <h1 class="order-status__title">{{ $title }}</h1>

        @if ($folio !== null)
            <p class="order-status__folio">#{{ $folio }}</p>
        @endif

        <p class="order-status__note">{{ $note }}</p>
    </div>
</div>
