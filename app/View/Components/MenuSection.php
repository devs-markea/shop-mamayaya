<?php

namespace App\View\Components;

use App\Support\Search;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A home page section: header (name and subtitle) and its cards. Port of
 * src/components/MenuSection.astro.
 *
 *   featured  a horizontal list of <x-product-card>, swiped or moved with the arrows
 *             (resources/js/scripts/featured-carousel.ts)
 *   category  a panel category, as a grid of <x-menu-card>
 */
class MenuSection extends Component
{
    /** The first six featured dishes carry their rank on the photo: "#1 favoritos". */
    public const RANKED = 6;

    public int $ranked = self::RANKED;

    public bool $featured;

    public string $countLabel;

    public string $titleId;

    public string $itemsId;

    /**
     * @param  array{anchor: string, kind: string, name: string, description: string, products: list<array>}  $section
     * @param  bool  $eager  The first card isn't lazy-loaded: it's in the first viewport.
     */
    public function __construct(public array $section, public bool $eager = false, public bool $storeClosed = false)
    {
        $this->featured = $section['kind'] === 'featured';

        $count = count($section['products']);
        $this->countLabel = $count.' '.($count === 1 ? 'producto' : 'productos');

        $this->titleId = "{$section['anchor']}-titulo";
        $this->itemsId = "{$section['anchor']}-platillos";
    }

    /**
     * What the desktop search finds in a dish: its name, description and category name,
     * normalized the same way the browser normalizes the query. Only in categories: the
     * featured dishes are in theirs too, and that's where they're found.
     */
    public function searchText(array $product): string
    {
        return Search::normalize("{$product['name']} {$product['description']} {$this->section['name']}");
    }

    public function render(): View
    {
        return view('components.menu-section');
    }
}
