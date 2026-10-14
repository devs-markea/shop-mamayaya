<?php

namespace App\Services\Store;

use App\Services\Cache\CacheTag;
use App\Services\Cache\ShopCache;
use App\Services\ShopApi\ShopApiClient;
use App\Support\Schedule;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The store's settings and schedule. Port of src/lib/store-config.ts.
 *
 *   GET /api/store            business data, cached for a day
 *   GET /api/store/schedule   schedule, cached 30 s (a week with SCHEDULE_FROM_SHIFTS)
 *
 * The API omits a whole block when it isn't configured, so the settings are merged with
 * config/store-fallback.php FIELD BY FIELD: the API wins and the fallback only fills gaps.
 * Some blocks are decisions rather than gaps (free shipping, tips, social links, the bank
 * account) and fall back whole or not at all.
 */
class StoreService
{
    /**
     * The networks the store can draw. A CLOSED list: `network` picks the icon, so an
     * unknown one is dropped instead of drawing a generic glyph.
     */
    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'whatsapp', 'tiktok', 'youtube', 'x'];

    /** Fallback labels, ONLY for the hand-written fallback: the API sends its own. */
    private const NETWORK_LABELS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'x' => 'X (Twitter)',
    ];

    private const PAYMENT_METHODS = ['bank_transfer', 'efectivo', 'mercado_pago'];

    /**
     * @param  array<string, mixed>  $fallback  config/store-fallback.php
     * @param  array{config: array{fresh: int, stale: int}, schedule: array{fresh: int, stale: int}, schedule_from_shifts: array{fresh: int, stale: int}}  $ttl
     */
    public function __construct(
        private readonly ShopApiClient $api,
        private readonly ShopCache $cache,
        private readonly array $fallback,
        private readonly bool $scheduleFromShifts,
        private readonly array $ttl,
    ) {}

    /**
     * The resolved settings. NEVER THROWS: without the API the store keeps selling with
     * the last good copy or, without one, with the fallback.
     *
     * Every screen reads the cached copy, the payment ones included (CLABE and WhatsApp
     * templates), so editing the panel REQUIRES a purge.
     */
    public function settings(): array
    {
        $remote = [];

        try {
            $remote = (array) $this->cache->remember(
                CacheTag::Config,
                'configuracion',
                $this->ttl['config']['fresh'],
                $this->ttl['config']['stale'],
                fn () => $this->api->get('/api/store'),
            );
        } catch (Throwable $e) {
            Log::error('[tienda] fallo GET /api/store, se usa el respaldo', ['error' => $e->getMessage()]);
        }

        $fallback = $this->fallback;
        $socialLinks = self::normalizeSocialLinks($remote['socialLinks'] ?? null);

        return [
            'name' => self::pick($remote['name'] ?? null, $fallback['name'] ?? null),
            'publicUrl' => self::pick($remote['publicUrl'] ?? null, $fallback['publicUrl'] ?? null),

            // The whole list or the fallback's, never mixed: the order is data.
            'socialLinks' => $socialLinks ?: self::normalizeSocialLinks($fallback['socialLinks'] ?? null),

            // The phone does fall back; the templates DON'T: an empty one leaves its
            // button inert instead of resurrecting a text the business deleted.
            'whatsapp' => [
                'phone' => self::pick($remote['whatsapp']['phone'] ?? null, $fallback['whatsapp']['phone'] ?? null),
                'templates' => self::resolveTemplates($remote['whatsapp']['templates'] ?? null),
                'items' => [
                    'showVariant' => ($remote['whatsapp']['items']['showVariant'] ?? null) === true,
                    'showCustomizations' => ($remote['whatsapp']['items']['showCustomizations'] ?? null) === true,
                ],
            ],

            'bankTransfer' => self::resolveBankTransfer($remote['bankTransfer'] ?? null)
                ?? self::resolveBankTransfer($fallback['bankTransfer'] ?? null),

            'location' => [
                'address' => self::pick($remote['location']['address'] ?? null, $fallback['location']['address'] ?? null),
                'shortAddress' => self::pick($remote['location']['shortAddress'] ?? null, $fallback['location']['shortAddress'] ?? null),
                'mapsUrl' => self::pick($remote['location']['mapsUrl'] ?? null, $fallback['location']['mapsUrl'] ?? null),
                // HTML rewritten by the backend: no hand-written fallback can pass through it.
                'message' => self::pick($remote['location']['message'] ?? null, null),
            ],

            'delivery' => [
                // A decision, not a gap: the fallback only when the whole block is missing.
                'freeShipping' => $remote['delivery']['freeShipping'] ?? $fallback['delivery']['freeShipping'] ?? null,
                'estimate' => self::pick($remote['delivery']['estimate'] ?? null, $fallback['delivery']['estimate'] ?? null),
            ],

            // Each estimate falls back to ITS OWN fallback, never to the other mode's.
            'pickup' => [
                'estimate' => self::pick($remote['pickup']['estimate'] ?? null, $fallback['pickup']['estimate'] ?? null),
            ],

            'tips' => $remote['tips'] ?? $fallback['tips'] ?? null,

            'legal' => [
                'termsUrl' => self::pick($remote['legal']['termsUrl'] ?? null, $fallback['legal']['termsUrl'] ?? null),
            ],

            'home' => [
                'panel' => ['presentation' => self::pick($remote['home']['panel']['presentation'] ?? null, null)],
                'banner' => [
                    'title' => self::pick($remote['home']['banner']['title'] ?? null, $fallback['home']['banner']['title'] ?? null),
                    'description' => self::pick($remote['home']['banner']['description'] ?? null, $fallback['home']['banner']['description'] ?? null),
                    'image' => self::pick($remote['home']['banner']['image'] ?? null, $fallback['home']['banner']['image'] ?? null),
                ],
                'featured' => [
                    'title' => self::pick($remote['home']['featured']['title'] ?? null, null),
                    'description' => self::pick($remote['home']['featured']['description'] ?? null, null),
                ],
            ],

            'footer' => self::resolveFooter($remote['footer'] ?? null),

            'categories' => self::normalizeCategories($remote['categories'] ?? null),
        ];
    }

    /**
     * The store schedule, or null when it couldn't be read: then the clock claims nothing.
     *
     * With SCHEDULE_FROM_SHIFTS on, `isOpen`, `closesAt` and `opensAt` are recomputed ON
     * EVERY RENDER from the cached ranges, so the label is exact to the minute even when
     * the response is days old. Off, the server's `isOpen` is painted and it can only be
     * cached for 30 s; stale copies are only served when on (ranges don't go stale).
     */
    public function schedule(): ?array
    {
        $ttl = $this->scheduleFromShifts ? $this->ttl['schedule_from_shifts'] : $this->ttl['schedule'];

        try {
            $schedule = $this->cache->remember(
                CacheTag::Schedule,
                'horario',
                $ttl['fresh'],
                $ttl['stale'],
                fn () => $this->fetchSchedule(),
            );
        } catch (Throwable $e) {
            Log::error('[tienda] fallo GET /api/store/schedule', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $this->scheduleFromShifts) {
            return $schedule;
        }

        $resolved = Schedule::resolve($schedule['days']);

        // Without `dayOfWeek` there is nothing to compute: paint what came.
        if (! $resolved) {
            return $schedule;
        }

        return [
            ...$schedule,
            'isOpen' => $resolved['isOpen'],
            'closesAt' => $resolved['closesAt'],
            'opensAt' => $resolved['opensAt'],
        ];
    }

    /**
     * The real read. While both implementations of the rule coexist, the computed state is
     * compared with the server's here and any difference is logged.
     */
    private function fetchSchedule(): array
    {
        $schedule = Schedule::normalize((array) $this->api->get('/api/store/schedule'));

        $resolved = Schedule::resolve($schedule['days']);
        $mismatch = $resolved ? Schedule::mismatch($schedule, $resolved) : null;

        if ($mismatch) {
            Log::warning("[horario] el calculo desde los rangos no coincide con el servidor: {$mismatch}");
        }

        return $schedule;
    }

    /**
     * A value if it has content; otherwise the fallback's. An empty string saved in the
     * panel is as "unconfigured" as a missing field.
     */
    private static function pick(mixed $value, mixed $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : (is_string($fallback) ? trim($fallback) : '');
    }

    /**
     * Categories with an id and a name, ids as strings (so they compare like markup values).
     *
     * @return list<array{id: string, name: string}>
     */
    private static function normalizeCategories(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $categories = [];

        foreach ($value as $entry) {
            $id = $entry['id'] ?? null;
            $name = is_string($entry['name'] ?? null) ? trim($entry['name']) : '';

            if ((! is_string($id) && ! is_int($id)) || (string) $id === '' || $name === '') {
                continue;
            }

            $categories[] = ['id' => (string) $id, 'name' => $name];
        }

        return $categories;
    }

    /**
     * The bank account, whole or nothing: half an account is useless to pay and good for
     * distrust, so it is never stitched together from the API and the fallback.
     *
     * @return array{holder: string, bank: string, clabe: string}|null
     */
    private static function resolveBankTransfer(mixed $account): ?array
    {
        $holder = self::pick($account['holder'] ?? null, null);
        $bank = self::pick($account['bank'] ?? null, null);
        $clabe = self::pick($account['clabe'] ?? null, null);

        return $holder !== '' && $bank !== '' && $clabe !== '' ? compact('holder', 'bank', 'clabe') : null;
    }

    /**
     * The panel's templates, reduced to the two levels of the current contract
     * (`delivery.<method>` and `pickup.<method>`), plus `orderStatus`.
     *
     * The flat per-method keys and `efectivo_pickup` are still absorbed here, at the
     * border, so screens see a single shape. `paymentProof`/`orderPlaced` (indexed by
     * screen) are not read. A box the business left empty is NOT filled.
     */
    private static function resolveTemplates(mixed $templates): array
    {
        if (! is_array($templates)) {
            return [];
        }

        $group = function (string $mode) use ($templates): array {
            $resolved = [];

            foreach (self::PAYMENT_METHODS as $method) {
                $template = self::pick($templates[$mode][$method] ?? null, null);

                if ($template === '') {
                    $template = $mode === 'pickup'
                        // Only cash ever had its own pickup key; the rest fall back to delivery.
                        ? ($method === 'efectivo' ? self::pick($templates['efectivo_pickup'] ?? null, null) : '')
                        : self::pick($templates[$method] ?? null, null);
                }

                if ($template !== '') {
                    $resolved[$method] = $template;
                }
            }

            return $resolved;
        };

        $delivery = $group('delivery');
        $pickup = $group('pickup');
        $orderStatus = self::pick($templates['orderStatus'] ?? null, null);

        return array_filter([
            'delivery' => $delivery ?: null,
            'pickup' => $pickup ?: null,
            'orderStatus' => $orderStatus ?: null,
        ]);
    }

    /**
     * The list as the footer paints it: in the order it came, without the entries that
     * can't be drawn (unknown network, missing or relative URL).
     *
     * @return list<array{network: string, label: string, url: string}>
     */
    private static function normalizeSocialLinks(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        $normalized = [];

        foreach ($links as $link) {
            $network = $link['network'] ?? null;
            $url = self::pick($link['url'] ?? null, null);

            if (! in_array($network, self::SOCIAL_NETWORKS, true) || ! preg_match('/^https?:\/\//i', $url)) {
                continue;
            }

            $normalized[] = [
                'network' => $network,
                'label' => self::pick($link['label'] ?? null, self::NETWORK_LABELS[$network]),
                'url' => $url,
            ];
        }

        return $normalized;
    }

    /**
     * The footer texts, every null turned into an empty string ("use your own"). No
     * fallback: the store's own texts live in the footer component.
     *
     * The schedule line is reduced to a decision the footer can read without doubt: it is
     * only `custom` when the mode says so AND there is text to paint; otherwise `shift`.
     */
    private static function resolveFooter(mixed $footer): array
    {
        $about = $footer['about'] ?? null;
        $customLabel = ($about['schedule']['mode'] ?? null) === 'custom'
            ? self::pick($about['schedule']['label'] ?? null, null)
            : '';

        $labels = [];

        foreach (self::SOCIAL_NETWORKS as $network) {
            $label = self::pick($footer['follow']['labels'][$network] ?? null, null);

            if ($label !== '') {
                $labels[$network] = $label;
            }
        }

        return [
            'about' => [
                'title' => self::pick($about['title'] ?? null, null),
                'locationLabel' => self::pick($about['locationLabel'] ?? null, null),
                'schedule' => $customLabel !== ''
                    ? ['mode' => 'custom', 'label' => $customLabel]
                    : ['mode' => 'shift', 'label' => ''],
                'closedLabel' => self::pick($about['closedLabel'] ?? null, null),
            ],
            'follow' => ['title' => self::pick($footer['follow']['title'] ?? null, null), 'labels' => $labels],
            'promo' => [
                'title' => self::pick($footer['promo']['title'] ?? null, null),
                'placeholder' => self::pick($footer['promo']['placeholder'] ?? null, null),
                'buttonLabel' => self::pick($footer['promo']['buttonLabel'] ?? null, null),
            ],
            // Empty: the footer builds the usual one with the year and the store name.
            'copyright' => self::pick($footer['copyright'] ?? null, null),
        ];
    }
}
