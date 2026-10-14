<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The home page's welcome banner, written by the panel (`home.banner`), not by code: a
 * campaign must not need a deploy. Port of src/components/Hero.astro.
 *
 * The three pieces work separately: text without photo is a text strip, photo without
 * text a bare photo (with the status label on top), and none of them means no banner.
 */
class Hero extends Component
{
    private const STATUS_LABEL = [
        'open' => 'ABIERTO · ENTREGA DISPONIBLE',
        'closed-now' => 'CERRADO POR EL MOMENTO',
        'closed-today' => 'CERRADO POR HOY',
    ];

    public bool $hasText;

    public ?string $photoStyle = null;

    public ?string $statusLabel;

    /**
     * @param  string|null  $status  open|closed-now|closed-today (Schedule::status()), or null.
     */
    public function __construct(
        public string $title = '',
        public string $description = '',
        string $image = '',
        public ?string $status = null,
    ) {
        $this->hasText = $title !== '' || $description !== '';

        // The URL ends up inside a `url()` in the style attribute: only http(s), and
        // quoted so a parenthesis or a semicolon in the path can't close the declaration.
        if (preg_match('/^https?:\/\//i', $image)) {
            $this->photoStyle = '--sf-hero-photo: url('.json_encode($image, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).')';
        }

        $this->statusLabel = $status ? self::STATUS_LABEL[$status] ?? null : null;
    }

    public function shouldRender(): bool
    {
        return $this->hasText || $this->photoStyle !== null;
    }

    public function render(): View
    {
        return view('components.hero');
    }
}
