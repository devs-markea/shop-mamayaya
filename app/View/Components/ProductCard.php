<?php

namespace App\View\Components;

use App\Services\Catalog\CatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The standing card of the home page's featured list. Port of
 * src/components/ProductCard.astro. <x-menu-card> tells the same with another structure;
 * what both paint is decided once in CatalogService::productCardView().
 *
 * Without a photo the card doesn't paint the box: the body takes its room.
 */
class ProductCard extends Component
{
    public bool $orderable;

    public ?string $notice;

    public ?array $promo;

    public string $displayName;

    /** @var array{src: string, alt: string}|null */
    public ?array $image;

    public string $href;

    /**
     * @param  bool  $eager  The first card isn't lazy-loaded: it's in the first viewport.
     * @param  bool  $storeClosed  Nothing is orderable; the reason label stays quiet and the "+" stays, dimmed.
     * @param  int|null  $rank  Its place among the featured dishes, for the "#1 favoritos" label.
     */
    public function __construct(
        CatalogService $catalog,
        public array $product,
        public bool $eager = false,
        public bool $storeClosed = false,
        public ?int $rank = null,
    ) {
        [
            'orderable' => $this->orderable,
            'notice' => $this->notice,
            'promo' => $this->promo,
            'displayName' => $this->displayName,
            'image' => $this->image,
        ] = $catalog->productCardView($product, $storeClosed);

        $this->href = CatalogService::productHref($product);
    }

    public function render(): View
    {
        return view('components.product-card');
    }
}
