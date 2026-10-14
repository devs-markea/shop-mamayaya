<?php

namespace App\View\Components;

use App\Support\Tips;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The tip selector of /mamayaya/pago: one radio per amount plus "Otro", whose value IS
 * the typed amount (resources/js/scripts/payment-summary.ts keeps it up to date). Port
 * of src/components/TipSelector.astro.
 *
 * When the selected amount isn't one of the buttons it was typed in "Otro": that option
 * is born checked and its field open with the value inside. The panel opens without
 * JavaScript, because the CSS reads the radio's state with :has().
 */
class TipSelector extends Component
{
    public bool $custom;

    public string $customValue;

    public int $maxDigits = Tips::MAX_DIGITS;

    /**
     * @param  list<int>  $amounts  Already normalized (App\Support\Tips): with the 0 in front.
     * @param  int  $selected  The preselected amount, in MXN.
     */
    public function __construct(public array $amounts, public int $selected = 0)
    {
        $this->custom = Tips::isCustom($selected, $amounts);
        $this->customValue = $this->custom ? (string) $selected : '';
    }

    public function render(): View
    {
        return view('components.tip-selector');
    }
}
