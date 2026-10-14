<?php

namespace App\View\Components;

use App\Services\Catalog\CatalogService;
use App\Support\CartView;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A reminder of what was ordered, on the closing screens (transfer and cash): nothing is
 * edited here, so the lines are text and not controls. Port of
 * src/components/OrderDetail.astro.
 *
 * ONE MARKUP, TWO SCREENS: the photo, the line amount and the configuration only exist on
 * desktop; on mobile their wrappers are `display: contents` and the line is the same row
 * of text as always.
 *
 * The breakdown only appears when there's something to explain (a discount, a shipping or
 * a tip): without any, the subtotal would be the total repeated. On desktop it isn't
 * painted, since the summary card next to it carries the same rows.
 */
class OrderDetail extends Component
{
    /** @var list<array{item: array, image: ?array, selection: string}> */
    public array $lines;

    public bool $breakdown;

    /**
     * @param  list<array>  $items  The session cart's items (the order doesn't exist yet).
     * @param  float|int  $total  What's transferred or paid, shipping and tip included.
     * @param  float|int|null  $subtotal  Products BEFORE discounting; the total by default.
     * @param  float|int  $discount  What promotions take off. 0 = no row.
     * @param  int  $tip  The chosen tip. 0 = no row.
     * @param  array{state: string, cost: float|int, km?: ?float}  $shipping  Already resolved.
     */
    public function __construct(
        CatalogService $catalog,
        public array $items,
        public float|int $total,
        public float|int|null $subtotal = null,
        public float|int $discount = 0,
        public int $tip = 0,
        public array $shipping = ['state' => 'none', 'cost' => 0],
    ) {
        $this->subtotal ??= $total;

        // The variant and customizations with dashes ("Grande - Guacamole x2"), built
        // with the same function that paints them in the cart so both can't disagree.
        $this->lines = array_map(fn (array $item) => [
            'item' => $item,
            'image' => $catalog->productImage($item),
            'selection' => CartView::describeSelection($item, ' - '),
        ], $items);

        $this->breakdown = $discount > 0 || $tip > 0 || $shipping['state'] !== 'none';
    }

    public function render(): View
    {
        return view('components.order-detail');
    }
}
