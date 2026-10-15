<?php

namespace App\Services\Catalog;

use App\Services\Cache\CacheTag;
use App\Services\Cache\ShopCache;
use App\Services\ShopApi\ShopApiClient;
use App\Services\ShopApi\ShopApiException;
use App\Support\Price;

/**
 * The catalog: `GET /api/products`, the home page's sections and what each card paints.
 * Port of src/lib/catalog.ts (listing half), src/lib/product-card.ts and
 * src/lib/product-image.ts.
 *
 * The backend calls this entity Menu and the store calls it Product: same `menus` row.
 * Two identifiers with two jobs: the `slug` ROUTES (`/mamayaya/{slug}`, and it can change)
 * and the `id` BUYS (the `productId` sent to the cart, and it never changes).
 *
 * Product shape (API, camelCase): {id, slug, name, description, category: {id, name,
 * description}, basePrice, image: {url, alt}, available, unavailableReason, promotion,
 * featured: ?{isFeatured, position}}.
 */
class CatalogService
{
    /**
     * Title and subtitle of the featured section when the business didn't write its own
     * (`home.featured` of GET /api/store).
     */
    public const FEATURED_SECTION = [
        'name' => 'Destacados',
        'description' => 'Los favoritos de nuestros clientes',
    ];

    /**
     * The placeholder the API serves today for dishes without a photo. It counts as "no
     * photo": the card closes the gap instead of painting a grey box.
     */
    private const PLACEHOLDER_IMAGE_PATH = '/images/placeholder-dish.svg';

    /** Why a published dish can't be bought right now. Unknown reasons get the generic text. */
    private const UNAVAILABLE_LABEL = [
        'no_price' => 'Sin precio',
        'out_of_schedule' => 'Fuera de horario',
    ];

    /** Card names are cut at 24 characters so the promo badge fits on the same line. */
    private const NAME_MAX = 24;

    /** The variants group has no name in the API (they are price rows): the copy lives here. */
    private const VARIANTS_LABEL = 'Elige una opción';

    /** Fallback name of an unnamed variant, so no row is left without a label. */
    public const VARIANT_FALLBACK_LABEL = 'Variante';

    /**
     * @param  array{fresh: int, stale: int}  $ttl
     */
    public function __construct(
        private readonly ShopApiClient $api,
        private readonly ShopCache $cache,
        private readonly array $ttl,
    ) {}

    /**
     * The whole published catalog. Throws ShopApiException when it can't be read and
     * there is no copy to serve.
     *
     * CACHED FOR A WHOLE DAY. What the clock moves (open or closed) doesn't come from
     * here but from the schedule, which is read apart and is accurate to the minute: see
     * productCardView(). Prices, additions and promotions arrive with a purge.
     *
     * The category is read when leaving the cache, not inside the load: what's stored is
     * the response as is, so a copy with the old shape (the category as a bare name) still
     * reads right.
     *
     * @return list<array<string, mixed>>
     */
    public function products(): array
    {
        $items = $this->cache->remember(
            CacheTag::Catalog,
            $this->ttl['fresh'],
            $this->ttl['stale'],
            fn () => $this->api->get('/api/products'),
        );

        return array_values(array_map(fn (array $item) => [
            ...$item,
            'category' => self::readCategory($item['category'] ?? ''),
            'featured' => $item['featured'] ?? null,
        ], is_array($items) ? $items : []));
    }

    /**
     * The featured dishes first (if any) and then one section per category, by id and in
     * the panel's order. Section shape: {anchor, kind: featured|category, name,
     * description, products}.
     *
     * @param  list<array{id: string, name: string}>  $listed  `store.categories`
     * @param  array{title?: string, description?: string}  $heading  `store.home.featured`
     * @return list<array<string, mixed>>
     */
    public function menuSections(array $items, array $listed = [], array $heading = []): array
    {
        $sections = [];

        $featured = array_values(array_filter($items, fn ($item) => ! empty($item['featured'])));
        // PHP's sort is stable: equal positions keep the API order.
        usort($featured, fn ($a, $b) => ($a['featured']['position'] ?? 0) <=> ($b['featured']['position'] ?? 0));

        // Without featured dishes there is no section and no index entry.
        if ($featured !== []) {
            $sections[] = [
                // Fixed: the index finds the section whatever the business writes.
                'anchor' => 'menu-destacados',
                'kind' => 'featured',
                'name' => ($heading['title'] ?? '') ?: self::FEATURED_SECTION['name'],
                'description' => ($heading['description'] ?? '') ?: self::FEATURED_SECTION['description'],
                'products' => $featured,
            ];
        }

        foreach (self::groupByCategory($items, $listed) as $group) {
            $sections[] = [
                'anchor' => 'menu-categoria-'.preg_replace('/[^\w-]/', '-', $group['category']['id']),
                'kind' => 'category',
                'name' => $group['category']['name'],
                'description' => $group['category']['description'],
                'products' => $group['products'],
            ];
        }

        return $sections;
    }

    /**
     * The product page: `GET /api/products/{slug}`, read fresh (not cached: it's what's
     * bought, and the backend revalidates on add anyway).
     *
     * Null on a 404, which covers four cases on purpose: the link doesn't exist, the admin
     * rewrote it (the old one doesn't redirect), it's unpublished, or it has no category.
     * Any other failure throws ShopApiException.
     *
     * Detail shape: {id, slug, name, description, category, image, available,
     * unavailableReason, basePrice, hasVariants, defaultVariantId, variants: list<{id, name,
     * price, finalPrice, savings, image}>, customizations: list<{id, name, control, required,
     * min, max, maxPerOption, defaultOptionId, options: list<{id, label, price}>}>, promotion}.
     */
    public function product(string $slug): ?array
    {
        $path = '/api/products/'.rawurlencode($slug);

        try {
            return (array) $this->api->get($path);
        } catch (ShopApiException $e) {
            if ($e->status === 404) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * The single shape <x-option-group> paints: the price variants and the customizations
     * are the same block on screen (header, required tag, list of choices).
     *
     * `kind` tells them apart: `variant` prices REPLACE the dish's price; `option` prices
     * are a surcharge that ADDS up.
     *
     * @return list<array<string, mixed>>
     */
    public function optionGroups(array $product): array
    {
        $groups = [];

        // With hasVariants false there is exactly one unnamed variant: no selector, but
        // its id still travels to the cart in a hidden field of the page.
        if (! empty($product['hasVariants'])) {
            $groups[] = [
                'id' => 'variant',
                'name' => 'variant',
                'label' => self::VARIANTS_LABEL,
                'control' => 'radio',
                'required' => true,
                'min' => 1,
                'max' => 1,
                'maxPerOption' => null,
                'kind' => 'variant',
                'choices' => array_map(fn (array $variant, int $index) => [
                    'id' => (string) $variant['id'],
                    'label' => (string) ($variant['name'] ?? ''),
                    'price' => $variant['price'],
                    'finalPrice' => $variant['finalPrice'] ?? null,
                    // defaultVariantId is the first one, like the panel's simulator.
                    'checked' => ! empty($product['defaultVariantId'])
                        ? (string) $variant['id'] === (string) $product['defaultVariantId']
                        : $index === 0,
                    // The same question as the dish photo: the placeholder counts as none.
                    'image' => $this->productImage($variant),
                ], $product['variants'], array_keys($product['variants'])),
            ];
        }

        foreach ($product['customizations'] ?? [] as $customization) {
            $groups[] = [
                'id' => (string) $customization['id'],
                'name' => "customization-{$customization['id']}",
                'label' => (string) $customization['name'],
                'control' => $customization['control'],
                'required' => (bool) $customization['required'],
                'min' => (int) $customization['min'],
                'max' => ($customization['max'] ?? null) === null ? null : (int) $customization['max'],
                'maxPerOption' => isset($customization['maxPerOption']) ? (int) $customization['maxPerOption'] : null,
                'kind' => 'option',
                'choices' => array_map(fn (array $option) => [
                    'id' => (string) $option['id'],
                    'label' => (string) $option['label'],
                    'price' => $option['price'],
                    // Customizations are charged whole, with or without a promotion.
                    'finalPrice' => null,
                    // Only radio groups bring a preselection; elsewhere it's null.
                    'checked' => (string) $option['id'] === (string) ($customization['defaultOptionId'] ?? ''),
                    'image' => null,
                ], $customization['options']),
            ];
        }

        return $groups;
    }

    /** The variant checked when the page opens; the initial total comes from it. */
    public static function defaultVariant(array $product): ?array
    {
        foreach ($product['variants'] ?? [] as $variant) {
            if ((string) $variant['id'] === (string) ($product['defaultVariantId'] ?? '')) {
                return $variant;
            }
        }

        return $product['variants'][0] ?? null;
    }

    /** What the variant really costs: the discounted price if there is one. */
    public static function variantPrice(?array $variant): float|int|null
    {
        return $variant === null ? null : ($variant['finalPrice'] ?? $variant['price']);
    }

    /**
     * The initial total: default variant (already discounted, or the button would promise
     * one amount and the cart charge another) plus the preselected options.
     */
    public static function initialTotal(array $product): float|int
    {
        $preselected = 0;

        foreach ($product['customizations'] ?? [] as $customization) {
            foreach ($customization['options'] as $option) {
                if ((string) $option['id'] === (string) ($customization['defaultOptionId'] ?? '')) {
                    $preselected += $option['price'];
                }
            }
        }

        return (self::variantPrice(self::defaultVariant($product)) ?? $product['basePrice']) + $preselected;
    }

    /** The product page route. Only the slug: `/mamayaya/{id}` resolves nothing. */
    public static function productHref(array $item): string
    {
        return '/mamayaya/'.rawurlencode((string) $item['slug']);
    }

    /**
     * Whether the dish has a photo of its own: covers a missing field, null, an empty
     * string and the API's placeholder.
     */
    public static function hasImage(mixed $image): bool
    {
        $url = is_array($image) ? ($image['url'] ?? null) : null;

        if (! is_string($url)) {
            return false;
        }

        // Without query or fragment: the placeholder may come with a version behind.
        $path = preg_split('/[?#]/', trim($url))[0] ?? '';

        return $path !== '' && ! str_ends_with($path, self::PLACEHOLDER_IMAGE_PATH);
    }

    /**
     * The photo ready for the <img>, or null when the dish has none: returning null (and
     * not a placeholder URL) is what lets the cards close the gap.
     *
     * @return array{src: string, alt: string}|null
     */
    public function productImage(array $item): ?array
    {
        if (! self::hasImage($item['image'] ?? null)) {
            return null;
        }

        // A missing alt makes the photo decorative (the name is written next to it).
        return ['src' => $this->api->assetUrl($item['image']['url']), 'alt' => (string) ($item['image']['alt'] ?? '')];
    }

    /**
     * What a home card paints, decided once so the two cards (featured and category)
     * can't contradict each other: {orderable, notice, promo, displayName, image}.
     *
     * THE SCHEDULE OVERRIDES `available`, BOTH WAYS. The catalog is cached for a day, so
     * its `available` is frozen at read time: a list read at dawn says "out of schedule"
     * for everything. The schedule is read fresh, so it decides:
     *
     *   store closed   nothing is orderable, whatever the catalog says
     *   store open     a cached "out_of_schedule" is ignored
     *
     * Any other reason is respected. With the store closed the reason label stays quiet:
     * the closed-store card already says it once.
     */
    public function productCardView(array $product, bool $storeClosed): array
    {
        $available = ($product['available'] ?? false) === true;
        $staleSchedule = ! $available && ($product['unavailableReason'] ?? null) === 'out_of_schedule';

        $orderable = ($available || $staleSchedule) && ! $storeClosed;

        $notice = $storeClosed || $available || $staleSchedule
            ? null
            : (self::UNAVAILABLE_LABEL[$product['unavailableReason'] ?? ''] ?? 'No disponible');

        $name = (string) $product['name'];
        $displayName = mb_strlen($name) > self::NAME_MAX
            ? rtrim(mb_substr($name, 0, self::NAME_MAX)).'...'
            : $name;

        return [
            'orderable' => $orderable,
            'notice' => $notice,
            'promo' => self::promotionView($product),
            'displayName' => $displayName,
            'image' => $this->productImage($product),
        ];
    }

    /**
     * Decides WHAT is painted from the promotion block the API sends. It computes no
     * amount: original, final and savings arrive resolved.
     *
     *   buy_get                     the badge ("2x1") and nothing else
     *   discount with savings       struck-through original + final (+ savings)
     *   discount without savings    a `special` that lowers some variants but not the
     *                               cheapest: labelled by how many it reaches
     *
     * @return array{label: string, detailLabel: string, name: string, original: float|int|null, price: float|int, savings: float|int, note: ?string}|null
     */
    public static function promotionView(array $item): ?array
    {
        $promotion = $item['promotion'] ?? null;

        if (! is_array($promotion)) {
            return null;
        }

        $discount = $promotion['discount'] ?? null;
        $buyGet = $promotion['buyGet'] ?? null;
        $base = $item['basePrice'];

        if (($promotion['type'] ?? null) === 'buy_get' || ! is_array($discount)) {
            $offer = is_array($buyGet) ? "Lleva {$buyGet['buy']} y paga {$buyGet['get']}" : null;

            return [
                'label' => $promotion['label'],
                'detailLabel' => $offer ?? $promotion['label'],
                'name' => $promotion['name'],
                'original' => null,
                'price' => $base,
                'savings' => 0,
                'note' => null,
            ];
        }

        $value = $discount['value'];
        $detailLabel = match ($discount['kind']) {
            'percentage' => "Descuento de {$value}%",
            'fixed' => 'Descuento de '.Price::format($value),
            // `special` FIXES the price: "Descuento de $150" on a $189 dish would lie.
            'special' => 'Precio especial de '.Price::format($value),
            default => $promotion['label'],
        };

        $partial = $discount['variantsTotal'] > 1 && $discount['variantsAffected'] < $discount['variantsTotal']
            ? "Precio especial en {$discount['variantsAffected']} de {$discount['variantsTotal']} "
                .($discount['variantsTotal'] === 1 ? 'tamano' : 'tamanos')
            : null;

        if ($discount['savings'] <= 0) {
            return [
                'label' => $promotion['label'],
                'detailLabel' => $detailLabel,
                'name' => $promotion['name'],
                'original' => null,
                'price' => $base,
                'savings' => 0,
                'note' => $partial,
            ];
        }

        return [
            'label' => $promotion['label'],
            'detailLabel' => $detailLabel,
            'name' => $promotion['name'],
            'original' => $discount['originalPrice'],
            'price' => $discount['finalPrice'],
            'savings' => $discount['savings'],
            'note' => $partial,
        ];
    }

    /**
     * The `{id, name, description}` group since 2026-10-05, or the bare name of before
     * (then the name acts as id, so the dish doesn't fall off the menu).
     */
    private static function readCategory(mixed $category): array
    {
        if (is_array($category)) {
            return [
                'id' => (string) ($category['id'] ?? ''),
                'name' => (string) ($category['name'] ?? ''),
                'description' => (string) ($category['description'] ?? ''),
            ];
        }

        return ['id' => (string) $category, 'name' => (string) $category, 'description' => ''];
    }

    /**
     * The dishes grouped by their category id, in the panel's order (`store.categories`).
     * Settings and catalog are cached apart, so mismatches resolve in favor of the
     * catalog: a listed category without dishes isn't painted, and dishes of an unlisted
     * category are painted after, in catalog order.
     */
    private static function groupByCategory(array $items, array $listed): array
    {
        $groups = [];

        foreach ($items as $item) {
            $id = $item['category']['id'];
            $groups[$id] ??= ['category' => $item['category'], 'products' => []];
            $groups[$id]['products'][] = $item;
        }

        $ordered = [];

        foreach ($listed as $entry) {
            if (! isset($groups[$entry['id']])) {
                continue;
            }

            $group = $groups[$entry['id']];
            $group['category']['name'] = $entry['name'];
            $ordered[] = $group;
            unset($groups[$entry['id']]);
        }

        return [...$ordered, ...array_values($groups)];
    }
}
