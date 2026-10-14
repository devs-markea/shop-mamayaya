<ul class="order-meta list-unstyled">
    @foreach ($items as $item)
        <li @class(['order-meta__item', 'order-meta__item--truncate' => $item['truncate'], 'd-none' => $item['hidden']]) data-mode="{{ $item['mode'] }}">
            <x-icon :name="$item['icon']" size="sm" class="order-meta__icon" />

            {{-- Name and value together: on desktop they would stack. On mobile the name
                 isn't painted and this stays the value, on the usual row. --}}
            <span class="order-meta__text">
                <span class="order-meta__name">{{ $item['name'] }}</span>

                @if ($item['sheet'])
                    <button class="order-meta__link btn-plain" type="button" aria-haspopup="dialog" data-sheet="{{ $item['sheet'] }}">{{ $item['value'] }}</button>
                @else
                    <span class="order-meta__value" @if ($item['truncate']) title="{{ $item['value'] }}" @endif>{{ $item['value'] }}</span>
                @endif
            </span>
        </li>
    @endforeach
</ul>
