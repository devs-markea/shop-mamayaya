<?php

namespace App\View\Components;

use App\Services\Cart\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The desktop bar: logo, then the store's metas and the cart chip. Port of
 * src/components/NavbarDesktop.astro. Not a variant of .navbar: other elements and
 * another layout, so another block.
 */
class NavbarDesktop extends Component
{
    /** @var list<array{icon: string, label: string, sheet: string, name: ?string, truncate: bool}> */
    public array $metas = [];

    /** @var list<array{mode: string, icon: string, name: string, value: string}> */
    public array $estimates = [];

    public string $cartLabel;

    /**
     * @param  bool  $hasLocation  Whether the header mounted the location sheet.
     * @param  bool  $hasSchedule  Whether the header mounted the schedule sheet.
     * @param  string|null  $shift  The current shift, already worded (Schedule::shiftLabel()).
     * @param  'delivery'|'pickup'  $selected  The mode the page arrives with: which estimate shows where there's no switch.
     */
    public function __construct(
        public bool $hasLocation = false,
        public bool $hasSchedule = false,
        public ?string $shift = null,
        public string $estimate = '',
        public string $pickup = '',
        public string $selected = 'delivery',
        public float|int $cartTotal = 0,
        public int $cartCount = 0,
    ) {
        // The pin labels what it opens, not the address: the sheet shows the full one.
        if ($hasLocation) {
            $this->metas[] = ['icon' => 'map-pin', 'label' => 'Ubicación', 'sheet' => 'sheet-location', 'name' => null, 'truncate' => false];
        }

        // The clock labels the current shift; what it opens goes in its accessible name.
        // Without a shift to label it says "Horarios", which already says it.
        if ($hasSchedule) {
            $this->metas[] = [
                'icon' => 'clock',
                'label' => $shift ?: 'Horarios',
                'sheet' => 'sheet-schedule',
                'name' => $shift ? 'Horarios' : null,
                'truncate' => true,
            ];
        }

        // One estimate per delivery mode, both in the markup; CSS shows the chosen one.
        if ($estimate !== '') {
            $this->estimates[] = ['mode' => 'delivery', 'icon' => 'moto', 'name' => 'Tiempo estimado de entrega', 'value' => $estimate];
        }

        if ($pickup !== '') {
            $this->estimates[] = ['mode' => 'pickup', 'icon' => 'store', 'name' => 'Tiempo estimado de recolección', 'value' => $pickup];
        }

        $this->cartLabel = CartService::chipLabel($cartTotal, $cartCount);
    }

    /** The label goes FIRST in the accessible name; the shift may already end in "h.". */
    public function metaName(array $meta): ?string
    {
        if (! $meta['name']) {
            return null;
        }

        return $meta['label'].(str_ends_with($meta['label'], '.') ? '' : '.').' '.$meta['name'];
    }

    public function render(): View
    {
        return view('components.navbar-desktop');
    }
}
