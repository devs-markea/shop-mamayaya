<?php

namespace App\Support;

/**
 * Shipping as the store tells it. Port of resources/js/lib/shipping.ts (which the
 * browser keeps using to repaint the summary): both must resolve and word the same state.
 *
 * NO PRICE IS COMPUTED HERE. The amount, the distance, the city and the notice come from
 * the backend (`/api/shipping/quote`). Only FREE SHIPPING is resolved here, because it's
 * earned by crossing a cart amount the browser already knows: the summary turns "Gratis"
 * on the "+" that crosses it, without a round trip.
 *
 * Quote shape: {cost: ?number, km: ?number, lat: ?number, lng: ?number, quoteId: string,
 * serviceable: bool, free: bool, notice: string}.
 * Result shape: {state: none|free|pending|quoted, cost: number, km?: ?number}.
 */
final class Shipping
{
    /** The notice when the backend marks a point out of area without a message. */
    public const AREA_NOTICE_FALLBACK = 'Entregamos a domicilio en Cancun, Quintana Roo. El costo de envio de tu pedido se confirmara al finalizarlo.';

    /**
     * A valid quote, or null. Two inputs come through here and neither is trustworthy:
     * the API's answer and the draft cookie the browser writes.
     */
    public static function parseQuote(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        $number = fn ($input) => (is_int($input) || is_float($input)) && is_finite($input) && $input >= 0 ? $input : null;
        $coordinate = fn ($input) => (is_int($input) || is_float($input)) && is_finite($input) ? $input : null;

        // Kilometers come with full float precision: cut to the decimal they're labelled with.
        $km = $number($value['km'] ?? null);

        return [
            'cost' => $number($value['cost'] ?? null),
            'km' => $km === null ? null : round($km * 10) / 10,
            'lat' => $coordinate($value['lat'] ?? null),
            'lng' => $coordinate($value['lng'] ?? null),
            'quoteId' => is_string($value['quoteId'] ?? null) ? $value['quoteId'] : '',
            // Only an explicit `false` leaves a point out of area.
            'serviceable' => ($value['serviceable'] ?? null) !== false,
            'free' => ($value['free'] ?? null) === true,
            'notice' => is_string($value['notice'] ?? null) ? $value['notice'] : '',
        ];
    }

    /**
     * From the `/api/shipping/quote` response to the store's quote. The point is set by
     * whoever asked: when recovering a session's quote it's unknown (null).
     */
    public static function quoteFromResponse(?array $data, ?array $asked = null): ?array
    {
        if (! $data) {
            return null;
        }

        $serviceable = ($data['serviceable'] ?? null) !== false;

        return self::parseQuote([
            'cost' => $data['cost'] ?? null,
            'km' => $data['distanceKm'] ?? null,
            'lat' => $asked['lat'] ?? null,
            'lng' => $asked['lng'] ?? null,
            'quoteId' => $data['quoteId'] ?? null,
            'serviceable' => $serviceable,
            'free' => ($data['free'] ?? null) === true,
            'notice' => $serviceable ? '' : (($data['message'] ?? '') ?: self::AREA_NOTICE_FALLBACK),
        ]);
    }

    /**
     * Which shipping this purchase shows. The order of the answers isn't casual: pickup
     * has none; out of area there's nothing to promise (before free shipping on purpose);
     * free shipping wins over the amount; then the amount; without one, "Por cotizar".
     *
     * @param  array{mode: string, threshold: ?number}|null  $freeShipping
     */
    public static function resolve(bool $pickup, ?array $quote, float|int $products, ?array $freeShipping): array
    {
        if ($pickup) {
            return ['state' => 'none', 'cost' => 0];
        }

        if ($quote && ! $quote['serviceable']) {
            return ['state' => 'pending', 'cost' => 0];
        }

        if ($quote['free'] ?? false) {
            return ['state' => 'free', 'cost' => 0];
        }

        if (($freeShipping['mode'] ?? null) === 'always') {
            return ['state' => 'free', 'cost' => 0];
        }

        if (($freeShipping['mode'] ?? null) === 'threshold'
            && ($freeShipping['threshold'] ?? null) !== null
            && $freeShipping['threshold'] > 0
            && $products >= $freeShipping['threshold']) {
            return ['state' => 'free', 'cost' => 0];
        }

        if (($quote['cost'] ?? null) === null) {
            return ['state' => 'pending', 'cost' => 0];
        }

        return ['state' => 'quoted', 'cost' => $quote['cost'], 'km' => $quote['km']];
    }

    /** How shipping is labelled in a summary. `none` has no label: its row isn't painted. */
    public static function label(array $shipping): string
    {
        return match ($shipping['state']) {
            'free' => 'Gratis',
            'pending' => 'Por cotizar',
            default => Price::format($shipping['cost']),
        };
    }

    /**
     * "8.4 km": the distance next to the amount, so it can be checked against the rate.
     * Like `Intl.NumberFormat('es-MX', {maximumFractionDigits: 1})`: one decimal at most,
     * none when it's zero, and a comma between thousands.
     */
    public static function formatKm(float|int $km): string
    {
        $formatted = number_format((float) $km, 1, '.', ',');

        return (str_ends_with($formatted, '.0') ? substr($formatted, 0, -2) : $formatted).' km';
    }

    /** What the progress bar towards free shipping says. */
    public static function freeShippingLabel(float|int $remaining): string
    {
        if ($remaining <= 0) {
            return "¡Ya tienes envio gratis! \u{1F389}";
        }

        return 'Estas a '.Price::format($remaining)." de obtener envio gratis \u{1F389}";
    }

    /** How much is left for free shipping. 0 once reached. */
    public static function remainingForFreeShipping(float|int $total, float|int $threshold): float|int
    {
        return max(0, $threshold - $total);
    }

    /** Progress towards free shipping, as a percentage capped at 100. */
    public static function progress(float|int $total, float|int $threshold): int
    {
        if ($threshold <= 0) {
            return 100;
        }

        // Math.round(): half up, also for .5 (PHP's round() rounds half away from zero,
        // which is the same for these positive values).
        return (int) min(100, round(($total / $threshold) * 100));
    }
}
