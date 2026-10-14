{{--
    A 44px field with its accessible label (the placeholder isn't a label). Port of
    src/components/TextField.astro.

    `label` is omitted ONLY when whoever wraps the field writes it, because the label
    belongs to the group (the phone, split between country and number; the fields that
    carry their label above them on desktop): that label points here with for="field-<name>".
--}}
@props([
    'name',
    'label' => null,
    'placeholder',
    'type' => 'text',
    'autocomplete' => null,
    'required' => false,
    'inputmode' => null,
])

@if ($label)
    <label class="visually-hidden" for="field-{{ $name }}">{{ $label }}</label>
@endif
<input
    class="field form-control"
    id="field-{{ $name }}"
    name="{{ $name }}"
    type="{{ $type }}"
    placeholder="{{ $placeholder }}"
    @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
    @if ($inputmode) inputmode="{{ $inputmode }}" @endif
    @required($required)
/>
