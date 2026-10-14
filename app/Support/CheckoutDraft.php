<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * The server half of the checkout draft: the `sf_checkout` cookie the browser writes
 * (resources/js/lib/checkout-draft.ts) and the server reads to render each screen with
 * the buyer's choices already in place. Port of parseDraft() and its rules.
 *
 * Draft shape: {deliveryType: delivery|pickup, paymentMethod: ?string, tip: int,
 * customer: {name, phone, ...}, locationLabel: string, comments: string, shipping: ?quote,
 * idempotencyKey: string}.
 */
final class CheckoutDraft
{
    /** One day: enough to come back to the closing screen from the history. */
    public const ORDER_MAX_AGE = 60 * 60 * 24;

    /** Digits of the "Otro" tip field, and with them its cap (99999). */
    public const TIP_MAX = 99999;

    private const EMPTY = [
        'deliveryType' => 'delivery',
        'paymentMethod' => null,
        'tip' => 0,
        'customer' => ['name' => '', 'phone' => ''],
        'locationLabel' => '',
        'comments' => '',
        'shipping' => null,
        'idempotencyKey' => '',
    ];

    /**
     * The draft, validated field by field. A missing, tampered or older cookie starts
     * from scratch.
     *
     * PHP already URL-decodes cookie values once. The browser writes
     * `encodeURIComponent(JSON)`, so what arrives here is normally plain JSON; a second
     * decode is tried for values that were encoded twice.
     */
    public static function parse(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return self::EMPTY;
        }

        $parsed = json_decode($raw, true);

        if (! is_array($parsed)) {
            $parsed = json_decode(rawurldecode($raw), true);
        }

        if (! is_array($parsed)) {
            return self::EMPTY;
        }

        return [
            ...self::EMPTY,
            ...$parsed,
            // The two contract values and nothing else: an invented mode would leave the
            // switch with nothing checked and come back from checkout as a 422.
            'deliveryType' => ($parsed['deliveryType'] ?? null) === 'pickup' ? 'pickup' : 'delivery',
            // The only amount that comes from this cookie and is added to the total.
            'tip' => self::parseTipAmount($parsed['tip'] ?? null) ?? 0,
            // Never replaced whole: missing fields stay empty, not undefined.
            'customer' => [...self::EMPTY['customer'], ...(is_array($parsed['customer'] ?? null) ? $parsed['customer'] : [])],
            'comments' => is_string($parsed['comments'] ?? null) ? $parsed['comments'] : '',
            // Validated whole: a negative or textual amount would label shipping with
            // whatever the cookie says. Without a valid one, "Por cotizar".
            'shipping' => Shipping::parseQuote($parsed['shipping'] ?? null),
        ];
    }

    /** The delivery mode chosen so far. */
    public static function deliveryType(?string $raw): string
    {
        return self::parse($raw)['deliveryType'];
    }

    /**
     * Whether the draft carries a shared location. Shipping is quoted AGAINST A POINT:
     * without `locationUrl` there's no shipping to show however much the cookie remembers.
     */
    public static function hasSharedLocation(array $draft): bool
    {
        return trim((string) ($draft['customer']['locationUrl'] ?? '')) !== '';
    }

    /**
     * The pointer to the last order (the `sf_order` cookie): {id, method, deliveryType?,
     * chargeStarted?, seen?}, or null. Port of parseOrderPointer().
     *
     * Without `id` and `method` there's no pointer. The optional fields are copied only
     * when they come with a valid value: "unknown" and "false" aren't the same thing.
     */
    public static function parseOrderPointer(?string $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $parsed = json_decode($raw, true);

        if (! is_array($parsed)) {
            $parsed = json_decode(rawurldecode($raw), true);
        }

        // JavaScript's truthiness, not empty(): a "0" id is still an id there.
        $missing = fn (mixed $value) => in_array($value, [null, false, '', 0, 0.0], true);

        if (! is_array($parsed) || $missing($parsed['id'] ?? null) || $missing($parsed['method'] ?? null)) {
            return null;
        }

        $pointer = ['id' => $parsed['id'], 'method' => $parsed['method']];

        if (in_array($parsed['deliveryType'] ?? null, ['delivery', 'pickup'], true)) {
            $pointer['deliveryType'] = $parsed['deliveryType'];
        }

        foreach (['chargeStarted', 'seen'] as $flag) {
            if (is_bool($parsed[$flag] ?? null)) {
                $pointer[$flag] = $parsed[$flag];
            }
        }

        return $pointer;
    }

    /**
     * The order pointer marked as seen: the only cookie this flow writes from the server.
     * Port of markPointerSeen().
     *
     * Emitted with the SAME attributes the browser used to create it
     * (resources/js/lib/checkout-draft.ts): another path or max-age wouldn't update the
     * cookie, they'd create a second one with the same name. Not httpOnly, like the
     * browser's, and not encrypted (bootstrap/app.php). The value goes as plain JSON: the
     * cookie encodes it when serialized, as encodeURIComponent() does in the browser, so
     * parseOrderPointer() reads it the same whoever wrote it. The rest of the pointer is
     * kept: the acknowledgement still needs it.
     */
    public static function seenPointerCookie(array $pointer, bool $secure): Cookie
    {
        return Cookie::create(
            config('shop.cookies.order'),
            json_encode([...$pointer, 'seen' => true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            time() + self::ORDER_MAX_AGE,
            path: '/',
            secure: $secure,
            httpOnly: false,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }

    /** The useful digits of a phone, without area code signs, spaces or symbols. */
    public static function phoneDigits(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * What the draft still lacks to close an order, worded for the notices ("tu nombre",
     * "la calle"...). Empty when nothing is missing. Port of draftGaps().
     *
     * @return list<string>
     */
    public static function draftGaps(array $draft): array
    {
        $customer = $draft['customer'];
        $filled = fn (string $field) => trim((string) ($customer[$field] ?? '')) !== '';
        $gaps = [];

        if (! $filled('name')) {
            $gaps[] = 'tu nombre';
        }

        $digits = self::phoneDigits((string) ($customer['phone'] ?? ''));

        if ($digits === '') {
            $gaps[] = 'tu telefono';
        } elseif (strlen($digits) < 10) {
            $gaps[] = 'un telefono valido de 10 digitos';
        }

        if ($draft['deliveryType'] === 'delivery') {
            foreach (['neighborhood' => 'la colonia', 'street' => 'la calle', 'exteriorNumber' => 'el numero exterior'] as $field => $gap) {
                if (! $filled($field)) {
                    $gaps[] = $gap;
                }
            }

            // The point and its amount are a single piece of data: see hasSharedLocation().
            if (! self::hasSharedLocation($draft)) {
                $gaps[] = 'tu ubicacion en el mapa';
            }
        }

        return $gaps;
    }

    /**
     * A valid tip, or null: an integer between 0 and TIP_MAX. Rejected rather than
     * clamped, since an out-of-range value doesn't come from someone choosing.
     */
    public static function parseTipAmount(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || ! is_numeric($value)) {
                return null;
            }

            $value = $value + 0;
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 0 || $value > self::TIP_MAX) {
            return null;
        }

        return $value;
    }
}
