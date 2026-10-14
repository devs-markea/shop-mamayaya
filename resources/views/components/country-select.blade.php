{{--
    The phone country code: a Bootstrap dropdown (opening, closing on outside click,
    keyboard navigation and aria-expanded), without .dropdown-toggle because its caret
    doesn't fit the spec's 75px. The value travels in a hidden input so it's sent with the
    form. Port of src/components/CountrySelect.astro; delivery-form.ts imports the dropdown.
--}}
@props(['name' => 'country_code'])

@php($default = \App\Support\Countries::default())

<div class="dropdown">
    <button class="country-select btn-plain" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Codigo de pais" data-country-toggle>
        <x-flag :country="$default['code']" class="country-select__flag" decorative />
        <span class="country-select__code" data-country-code>{{ $default['dialCode'] }}</span>
    </button>

    <ul class="dropdown-menu country-select__menu">
        @foreach (\App\Support\Countries::LIST as $country)
            <li>
                <button class="dropdown-item country-select__option" type="button" data-country-option data-code="{{ $country['code'] }}" data-dial-code="{{ $country['dialCode'] }}">
                    <x-flag :country="$country['code']" class="country-select__flag" decorative />
                    <span class="country-select__name">{{ $country['name'] }}</span>
                    <span class="country-select__dial">{{ $country['dialCode'] }}</span>
                </button>
            </li>
        @endforeach
    </ul>

    <input type="hidden" name="{{ $name }}" value="{{ $default['dialCode'] }}" data-country-value />
</div>
