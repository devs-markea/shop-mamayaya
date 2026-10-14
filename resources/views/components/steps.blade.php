{{--
    The steps to follow after confirming the order. Port of src/components/Steps.astro.
    The copy comes from the screen, where the method already branches; this only numbers
    them.
--}}
@props(['steps'])

<div class="steps">
    <x-section-label>Pasos a seguir</x-section-label>

    {{-- An ordered list: the order is the information. --}}
    <ol class="steps__list list-unstyled">
        @foreach ($steps as $step)
            <li class="steps__item">
                {{-- The number is painted, not inherited from the list marker (the spec gives
                     it its own box). aria-hidden so it isn't read twice: the <ol> numbers. --}}
                <span class="steps__number" aria-hidden="true">{{ $loop->iteration }}</span>

                <span class="steps__text">{{ $step }}</span>
            </li>
        @endforeach
    </ol>
</div>
