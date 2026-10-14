<?php

namespace App\View\Components;

use App\Services\Theme\Theme;
use App\Services\Theme\ThemeRepository;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The page shell: head, SEO, theme, font and the chat button. Port of
 * src/layouts/Layout.astro.
 */
class Layout extends Component
{
    private const DESCRIPTION = 'Mamá Yaya — pedido a domicilio o para recoger.';

    public string $documentTitle;

    public string $canonical;

    public Theme $theme;

    /**
     * @param  array<string, mixed>  $store  The resolved settings: `store.name` signs the tab.
     * @param  string|null  $title  The page's own part ("Mi pedido", the dish). The home has none.
     * @param  bool  $noindex  Out of search engines: the order screens are a person's session.
     * @param  string|null  $dish  The product page's dish, for the chat message.
     * @param  list<string>  $vite  The page's own Vite entries, besides the global ones.
     */
    public function __construct(
        ThemeRepository $themes,
        public array $store,
        public ?string $title = null,
        public ?string $description = null,
        public bool $noindex = false,
        public ?string $dish = null,
        public array $vite = [],
    ) {
        // The page's own description, or the store's when it has none.
        $this->description = $description ?: self::DESCRIPTION;

        // Page first and store second: a narrow tab cuts from the right, and the page is
        // what tells one tab from another.
        $this->documentTitle = implode(' — ', array_filter([$title, $store['name'] ?? null]));

        // Canonical against APP_URL, the store's real domain, whatever host served it.
        $this->canonical = rtrim((string) config('app.url'), '/').'/'.ltrim(request()->path(), '/');

        // The theme goes on <html> and nowhere below: that's where colors.css derives the
        // accent's tint and edge.
        $this->theme = $themes->get((string) config('shop.theme'));
    }

    public function render(): View
    {
        return view('components.layout');
    }
}
