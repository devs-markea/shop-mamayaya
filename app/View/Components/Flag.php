<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * 20x14 flags for the phone country selector: filled shapes with their own colors,
 * simplified (no coats of arms: at 20x14 they can't be told apart). Port of
 * src/components/Flag.astro; extend it together with App\Support\Countries.
 */
class Flag extends Component
{
    /** @var array<string, array{label: string, shapes: string}> */
    public const FLAGS = [
        'mx' => ['label' => 'Mexico', 'shapes' => '<rect width="6.667" height="14" fill="#006847"/><rect x="6.667" width="6.666" height="14" fill="#fff"/><rect x="13.333" width="6.667" height="14" fill="#ce1126"/>'],
        'us' => ['label' => 'Estados Unidos', 'shapes' => '<rect width="20" height="14" fill="#fff"/><g fill="#b22234"><rect width="20" height="2"/><rect y="4" width="20" height="2"/><rect y="8" width="20" height="2"/><rect y="12" width="20" height="2"/></g><rect width="9" height="8" fill="#3c3b6e"/>'],
        'br' => ['label' => 'Brasil', 'shapes' => '<rect width="20" height="14" fill="#009c3b"/><path d="M10 1.4 18.6 7 10 12.6 1.4 7Z" fill="#ffdf00"/><circle cx="10" cy="7" r="2.8" fill="#002776"/>'],
        'pe' => ['label' => 'Peru', 'shapes' => '<rect width="20" height="14" fill="#d91023"/><rect x="6.667" width="6.666" height="14" fill="#fff"/>'],
        'ar' => ['label' => 'Argentina', 'shapes' => '<rect width="20" height="14" fill="#74acdf"/><rect y="4.667" width="20" height="4.666" fill="#fff"/><circle cx="10" cy="7" r="1.4" fill="#f6b40e"/>'],
        'co' => ['label' => 'Colombia', 'shapes' => '<rect width="20" height="14" fill="#fcd116"/><rect y="7" width="20" height="3.5" fill="#003893"/><rect y="10.5" width="20" height="3.5" fill="#ce1126"/>'],
    ];

    /** @param  bool  $decorative  The country name is already written next to it. */
    public function __construct(public string $country, public bool $decorative = false) {}

    public function render(): View
    {
        return view('components.flag', ['flag' => self::FLAGS[$this->country]]);
    }
}
