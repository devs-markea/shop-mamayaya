<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The logo, in a single place. Port of src/components/BrandLogo.astro.
 *
 * The ORIGINAL file is served at full size (969x951), as the business asked, so the
 * header looks sharp at any zoom. One URL for the three places that paint it (both bars
 * and the footer): one request, the other two hit the cache. Each one sets the height
 * in CSS; width/height here only give the ratio, so the space is reserved early.
 */
class BrandLogo extends Component
{
    public const FILE = 'resources/images/logo-mama-yaya-magenta.png';

    /** @var array{0: int, 1: int}|null */
    private static ?array $size = null;

    public function render(): View
    {
        self::$size ??= array_slice(getimagesize(base_path(self::FILE)) ?: [0, 0], 0, 2);

        return view('components.brand-logo', ['width' => self::$size[0], 'height' => self::$size[1]]);
    }
}
