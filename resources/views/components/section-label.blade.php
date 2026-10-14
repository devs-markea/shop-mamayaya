{{--
    The label that names a group, the same piece all over the store ("Elige el tamaño",
    "Propina", "Método de pago"…). Port of src/components/SectionLabel.astro.

    `as`: the element, chosen from outside (span by default; h2 for a section, label for
    a field). `variant`: soft (lighter, inside a white card) or strong (a screen section
    heading on desktop). Any other attribute (id, for, data-*) passes through.
--}}
@props(['as' => 'span', 'variant' => null])

<{{ $as }} {{ $attributes->class(['section-label', "section-label--{$variant}" => $variant]) }}>{{ $slot }}</{{ $as }}>
