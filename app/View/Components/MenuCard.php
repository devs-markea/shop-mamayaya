<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;

/**
 * The category cards of the home page. Tells the same as <x-product-card> (decided in the
 * same place) with another desktop structure: text on the left, photo on the right with
 * the "+" on its corner. On mobile it is the usual card. The labels reuse
 * .product-card__* classes and their styles. Port of src/components/MenuCard.astro.
 */
class MenuCard extends ProductCard
{
    public function render(): View
    {
        return view('components.menu-card');
    }
}
