<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * An SVG file from resources/images, inlined in the markup so it can inherit
 * currentColor. The file's own root attributes act as defaults and the ones given to
 * the component win: what Astro did with `import Icon from './icon.svg'`.
 *
 *   <x-inline-svg src="icons/whatsapp.svg" class="chat-fab__glyph" width="28" height="28" />
 */
class InlineSvg extends Component
{
    /** @var array<string, array{defaults: array<string, string>, inner: string}> */
    private static array $files = [];

    public function __construct(public string $src) {}

    public function render(): View
    {
        return view('components.inline-svg', self::$files[$this->src] ??= self::read($this->src));
    }

    /** @return array{defaults: array<string, string>, inner: string} */
    private static function read(string $src): array
    {
        $svg = @file_get_contents(resource_path("images/{$src}"));

        if ($svg === false || ! preg_match('/<svg\b([^>]*)>(.*)<\/svg>/s', $svg, $match)) {
            throw new InvalidArgumentException("resources/images/{$src} is not a readable SVG.");
        }

        preg_match_all('/([\w:-]+)="([^"]*)"/', $match[1], $pairs, PREG_SET_ORDER);

        return [
            'defaults' => array_diff_key(array_column($pairs, 2, 1), ['xmlns' => true]),
            'inner' => trim($match[2]),
        ];
    }
}
