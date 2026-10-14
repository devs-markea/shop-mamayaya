{{--
    "A domicilio" / "Para recoger": two radios with Bootstrap's .btn-check. Single choice
    without JS, arrow-key navigable and serializable in a form. Port of
    src/components/DeliverySwitch.astro.

    The `value`s are the design's ("domicilio" / "recoger"), not the contract's; the
    translation and the saving to the draft live in resources/js/lib/delivery-switch.ts.
--}}
@props(['selected' => 'delivery'])

@php
    $options = [
        ['id' => 'delivery-home', 'mode' => 'delivery', 'value' => 'domicilio', 'label' => 'A domicilio'],
        ['id' => 'delivery-pickup', 'mode' => 'pickup', 'value' => 'recoger', 'label' => 'Para recoger'],
    ];
@endphp

<div class="switch" role="group" aria-label="Modo de entrega" data-delivery-switch>
    @foreach ($options as $option)
        <input class="btn-check" type="radio" name="delivery" id="{{ $option['id'] }}" value="{{ $option['value'] }}" @checked($option['mode'] === $selected) autocomplete="off" />
        <label class="switch__option btn" for="{{ $option['id'] }}">{{ $option['label'] }}</label>
    @endforeach
</div>
