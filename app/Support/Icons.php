<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * The stroke icon system: 20x20 viewBox, stroke 2, round caps, currentColor.
 *
 * Same paths as resources/js/lib/icons.ts, which the browser uses to repaint the cart:
 * when an icon changes, change it in both. The <x-icon> component draws them in the
 * server markup.
 */
final class Icons
{
    public const PATHS = [
        'clock' => '<path d="M10 5v5l3.334 1.667M18.334 10a8.334 8.334 0 1 1-16.668 0 8.334 8.334 0 0 1 16.668 0Z"/>',
        'map-pin' => '<path d="M10.5 18.167C12.05 16.828 16.666 12.494 16.666 8.333a6.666 6.666 0 1 0-13.332 0c0 4.161 4.615 8.495 6.165 9.834a.833.833 0 0 0 1.001 0Z"/><circle cx="10" cy="8.333" r="2.5"/>',
        'share' => '<path d="M10 1.666V12.5M6.667 5 10 1.666 13.333 5M3.334 10v6.667c0 .442.176.866.488 1.179.312.312.736.488 1.178.488h9.999c.442 0 .866-.176 1.178-.488.313-.313.489-.737.489-1.179V10"/>',
        'truck' => '<path d="M11.667 15V5a.833.833 0 0 0-.834-.833H1.667A.833.833 0 0 0 .833 5v9.167c0 .46.373.833.834.833h1.666"/><path d="M12.5 15H7.5"/><path d="M15.833 15H17.5a.833.833 0 0 0 .833-.833v-3.042a.833.833 0 0 0-.183-.517l-2.9-3.625a.833.833 0 0 0-.65-.316h-2.933"/><circle cx="14.167" cy="15" r="1.667"/><circle cx="5.833" cy="15" r="1.667"/>',
        'moto' => '<circle cx="4.167" cy="14.167" r="2.5"/><circle cx="15.833" cy="14.167" r="2.5"/><path d="M2.5 8.333h5l2.083 5.834h3.75"/><path d="M11.25 4.167h2.5l1.515 7.5"/>',
        'store' => '<path d="M2.5 7.5 4.167 3.333h11.666L17.5 7.5"/><path d="M2.5 7.5a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0"/><path d="M3.75 10.417v5.416c0 .46.373.834.833.834h10.834c.46 0 .833-.373.833-.834v-5.416"/><path d="M8.333 16.667v-3.334h3.334v3.334"/>',
        'shopping-cart' => '<path d="M3.5 3.5H5.833L6.3 5.833M8.167 15.167h11.666L24.5 5.833H6.3M8.167 15.167 6.3 5.833M8.167 15.167l-2.675 2.675c-.735.735-.215 1.991.825 1.991h13.516M19.833 19.833a2.333 2.333 0 1 0 0 4.667 2.333 2.333 0 0 0 0-4.667ZM10.5 22.167A2.333 2.333 0 0 1 8.167 24.5a2.333 2.333 0 0 1 0-4.667 2.333 2.333 0 0 1 2.333 2.334Z"/>',
        'plus' => '<path d="M10 4.167v11.666M4.167 10h11.666"/>',
        'chevron-left' => '<path d="M12.5 15.833 6.667 10 12.5 4.167"/>',
        'arrow-left' => '<path d="M16.667 10H3.333M8.333 15l-5-5 5-5"/>',
        'arrow-sm-left' => '<path d="M15 10H5M9.167 14.167 5 10l4.167-4.167"/>',
        'arrow-sm-right' => '<path d="M5 10h10M10.833 5.833 15 10l-4.167 4.167"/>',
        'search' => '<path d="m17.5 17.5-5-5m1.667-4.167a5.833 5.833 0 1 1-11.667 0 5.833 5.833 0 0 1 11.667 0Z"/>',
        'list' => '<path d="M7.5 5h9.167M7.5 10h9.167M7.5 15h9.167M3.333 5h.008M3.333 10h.008M3.333 15h.008"/>',
        'trash' => '<path d="M2.5 5.833h15M7.5 5.833V4.167a.833.833 0 0 1 .833-.834h3.334a.833.833 0 0 1 .833.834v1.666"/><path d="M15.833 5.833 15.14 16.24a1.667 1.667 0 0 1-1.663 1.427H6.523A1.667 1.667 0 0 1 4.86 16.24L4.167 5.833"/><path d="M8.333 9.167v5M11.667 9.167v5"/>',
        'phone' => '<rect x="5" y="2" width="10" height="16" rx="2"/><path d="M8.75 15h2.5"/>',
        'credit-card' => '<rect x="1.667" y="4.167" width="16.666" height="11.666" rx="1.667"/><path d="M1.667 8.333h16.666"/>',
        'cash' => '<rect x="1.667" y="5" width="16.666" height="10" rx="1.667"/><circle cx="10" cy="10" r="2.083"/>',
        'copy' => '<rect x="6.667" y="6.667" width="11.666" height="11.666" rx="1.667"/><path d="M3.333 13.333a1.667 1.667 0 0 1-1.666-1.666V3.333c0-.92.746-1.666 1.666-1.666h8.334c.92 0 1.666.746 1.666 1.666"/>',
        'check' => '<path d="M16.667 5 7.5 14.167 3.333 10"/>',
        'info' => '<circle cx="10" cy="10" r="8.333"/><path d="M10 13.333V10M10 6.667h.008"/>',
        'tag' => '<path d="M10.488 2.155a1.667 1.667 0 0 0-1.178-.488H3.333a1.667 1.667 0 0 0-1.666 1.666V9.31c0 .442.175.866.488 1.178l7.253 7.254a2.022 2.022 0 0 0 2.859 0l5.483-5.483a2.022 2.022 0 0 0 0-2.859Z"/><path d="M6.25 6.25h.008"/>',
        'close' => '<path d="M15 5 5 15M5 5l10 10"/>',
        'crosshair' => '<circle cx="10" cy="10" r="6.667"/><circle cx="10" cy="10" r="1.667"/><path d="M10 1.667v2.5M10 15.833v2.5M18.333 10h-2.5M4.167 10h-2.5"/>',
    ];

    /**
     * Icons drawn on another grid. Only the cart: it came from the design drawn at 28
     * with stroke 2, and rescaling it would thicken the stroke when painted at 28.
     */
    private const VIEWBOXES = [
        'shopping-cart' => '0 0 28 28',
    ];

    /**
     * The px of each size, also emitted as width/height ATTRIBUTES: without them a bare
     * <svg> measures 300x150 until the stylesheet arrives, and the page jumps.
     */
    private const PX = ['sm' => 16, 'md' => 20, 'lg' => 40];

    public static function path(string $name): string
    {
        return self::PATHS[$name] ?? throw new InvalidArgumentException("Unknown icon [{$name}].");
    }

    public static function viewBox(string $name): string
    {
        return self::VIEWBOXES[$name] ?? '0 0 20 20';
    }

    public static function px(string $size = 'md'): int
    {
        return self::PX[$size] ?? self::PX['md'];
    }

    /**
     * The icon as a string, for markup built in PHP (CartView). The same output as
     * iconHtml() in resources/js/lib/icons.ts, which repaints it in the browser.
     */
    public static function html(string $name, string $size = 'md'): string
    {
        $px = self::px($size);

        return '<svg class="'.self::cssClass($size).'" width="'.$px.'" height="'.$px.'" viewBox="'.self::viewBox($name).'" aria-hidden="true" focusable="false">'.self::path($name).'</svg>';
    }

    public static function cssClass(string $size = 'md'): string
    {
        return match ($size) {
            'sm' => 'icon icon--sm',
            'lg' => 'icon icon--lg',
            default => 'icon',
        };
    }
}
