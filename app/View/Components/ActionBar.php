<?php

namespace App\View\Components;

use App\Support\Price;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The bottom action bar with the screen's main action. Port of
 * src/components/ActionBar.astro.
 *
 * With `href` the control is a link; without it, a submit. A `{total}` in the label with
 * a `total` given writes the template into `data-total-template`, so the order form can
 * recompute it: the copy lives in the markup, not in the script.
 */
class ActionBar extends Component
{
    public bool $hasTotal;

    public string $text;

    public string $buttonClass;

    public string $secondaryClass;

    /**
     * @param  string|null  $icon  A brand glyph left of the label (BrandIcon names).
     * @param  'primary'|'whatsapp'  $variant  Gold by default; `whatsapp` only to send the order.
     * @param  string|null  $note  A short notice under the button.
     * @param  bool  $disabled  Truly off (neither navigates nor submits): for what doesn't exist now, not for what's missing.
     * @param  array{label: string, href?: string, icon?: string, variant?: string}|null  $secondary  A second action below.
     */
    public function __construct(
        public string $label,
        public float|int|null $total = null,
        public ?string $href = null,
        public ?string $icon = null,
        public string $variant = 'primary',
        public ?string $note = null,
        public bool $disabled = false,
        public ?array $secondary = null,
    ) {
        $this->hasTotal = $total !== null && str_contains($label, '{total}');
        $this->text = $this->hasTotal ? str_replace('{total}', Price::format($total), $label) : $label;

        $this->buttonClass = self::classFor($variant);
        $this->secondaryClass = self::classFor($secondary['variant'] ?? 'primary', 'action-bar__button--secondary');
    }

    private static function classFor(string $color, ?string $modifier = null): string
    {
        return implode(' ', array_filter([
            'action-bar__button',
            'btn',
            'btn-primary',
            $color === 'whatsapp' ? 'action-bar__button--whatsapp' : null,
            $modifier,
        ]));
    }

    public function render(): View
    {
        return view('components.action-bar');
    }
}
