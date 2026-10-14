<?php

namespace App\View\Components;

use App\Support\Icons;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A stroke icon of the system (App\Support\Icons): inline SVG that inherits its color
 * with currentColor, so button states need no extra rules.
 */
class Icon extends Component
{
    /** @param  'sm'|'md'|'lg'  $size */
    public function __construct(public string $name, public string $size = 'md') {}

    public function render(): View
    {
        return view('components.icon', [
            'iconClass' => Icons::cssClass($this->size),
            // Also as attributes: without them a bare <svg> is 300x150 until the CSS loads.
            'px' => Icons::px($this->size),
            'viewBox' => Icons::viewBox($this->name),
            'paths' => Icons::path($this->name),
        ]);
    }
}
