<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The home page's metas (mobile only): two per delivery mode in the same place, and the
 * switch above decides which pair shows. Both are in the markup and CSS swaps them. Port
 * of src/components/OrderMeta.astro.
 *
 * Each meta is optional on its own: promising "25-35 min" or "Envío gratis" by default
 * is exactly what a home page must not do.
 */
class OrderMeta extends Component
{
    /** @var list<array{mode: string, icon: string, name: string, value: string, sheet: ?string, truncate: bool, hidden: bool}> */
    public array $items = [];

    public function __construct(
        public string $estimate = '',
        public ?string $shipping = null,
        public string $pickup = '',
        public bool $hasLocation = false,
        public string $shortAddress = '',
    ) {
        $meta = fn (string $mode, string $icon, string $name, string $value, array $extra = []) => [
            'mode' => $mode, 'icon' => $icon, 'name' => $name, 'value' => $value,
            'sheet' => null, 'truncate' => false, 'hidden' => false, ...$extra,
        ];

        if ($estimate !== '') {
            $this->items[] = $meta('delivery', 'clock', 'Tiempo envio', $estimate);
        }

        if ($shipping) {
            $this->items[] = $meta('delivery', 'truck', 'Costo envio', $shipping);
        }

        // The SHORT address, read only. HIDDEN for now (visual only): showing it again
        // is removing `hidden` here.
        if ($shortAddress !== '') {
            $this->items[] = $meta('delivery', 'map-pin', 'Ubicacion', $shortAddress, ['truncate' => true, 'hidden' => true]);
        }

        if ($pickup !== '') {
            $this->items[] = $meta('pickup', 'clock', 'Recoleccion', $pickup);
        }

        // The header mounts the location sheet; without address or link it doesn't exist.
        if ($hasLocation) {
            $this->items[] = $meta('pickup', 'map-pin', 'Ubicacion', 'Ver mapa', ['sheet' => 'sheet-location']);
        }
    }

    public function shouldRender(): bool
    {
        return $this->items !== [];
    }

    public function render(): View
    {
        return view('components.order-meta');
    }
}
