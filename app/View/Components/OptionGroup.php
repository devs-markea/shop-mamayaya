<?php

namespace App\View\Components;

use App\Services\Catalog\CatalogService;
use App\Support\Options;
use App\Support\Price;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * One option group of the product page: the price variants or a customization, with
 * the same structure (header, required tag, rule hint and choices). It branches by
 * `control`: radio, checkbox or quantity (steppers). The total, both caps and the
 * missing minimum are handled by resources/js/scripts/order-form.ts. Port of
 * src/components/OptionGroup.astro.
 */
class OptionGroup extends Component
{
    public string $labelId;

    public string $errorId;

    /** @var array{control: string, min: int, max: ?int, maxPerOption: ?int} */
    public array $rule;

    /** `max: 0`: the group takes no option. Painted read-only; the cart would reject it. */
    public bool $inert;

    public string $hint;

    /** The header's tag already says "Opcional": it isn't repeated below. */
    public bool $showHint;

    /** `quantity` isn't an <input>; the other two match the input's type. */
    public string $inputType;

    public int $unitCap;

    /** @param  array<string, mixed>  $group  One of CatalogService::optionGroups() */
    public function __construct(public array $group)
    {
        $this->labelId = "{$group['id']}-label";
        $this->errorId = "{$group['id']}-error";

        $this->rule = [
            'control' => $group['control'],
            'min' => $group['min'],
            'max' => $group['max'],
            'maxPerOption' => $group['maxPerOption'],
        ];

        $this->inert = Options::isInert($this->rule);
        $this->hint = Options::ruleLabel($this->rule);
        $this->showHint = $this->hint !== Options::OPTIONAL_LABEL;
        $this->inputType = $group['control'] === 'checkbox' ? 'checkbox' : 'radio';
        $this->unitCap = Options::unitCap($this->rule);
    }

    /** Only variants fall back to a generic name: an unnamed variant must still be pointable. */
    public function choiceName(string $label): string
    {
        return $this->group['kind'] === 'variant' ? ($label !== '' ? $label : CatalogService::VARIANT_FALLBACK_LABEL) : $label;
    }

    /**
     * A variant's price IS the dish's price: no sign, shown even at 0. An option's is a
     * surcharge: with `+`, and nothing at 0. The asymmetry is deliberate. The leading
     * space belongs to the separator, not to the markup.
     */
    public function choicePrice(float|int $price): string
    {
        if ($this->group['kind'] === 'variant') {
            return ' — '.Price::format($price);
        }

        return $price > 0 ? ' + '.Price::format($price) : '';
    }

    /** The price that COUNTS for the total: the discounted one when the promotion reaches it. */
    public function effectivePrice(array $choice): float|int
    {
        return $choice['finalPrice'] ?? $choice['price'];
    }

    public function render(): View
    {
        return view('components.option-group');
    }
}
