<?php

namespace App\Support;

/**
 * The rules of the product page's three option controls: radio, checkbox and quantity.
 * Server half of resources/js/lib/options.ts, which the order form keeps using in the
 * browser: both must word and cap groups the same way.
 *
 * Rule shape: {control: radio|checkbox|quantity, min: int, max: ?int, maxPerOption: ?int}.
 * Both caps count UNITS; what tells them apart is the scope: `min`/`max` are the GROUP's
 * units, `maxPerOption` those of ONE option (quantity only).
 */
final class Options
{
    /** The API's shape cap for `options.*.quantity` in POST /api/cart/items. */
    public const MAX_QUANTITY = 99;

    public const INERT_LABEL = 'No se puede elegir ninguna opcion';

    /** A group without minimum or cap. The header's tag already says it. */
    public const OPTIONAL_LABEL = 'Opcional';

    /**
     * How far ONE option's counter can go, ignoring what the others spent: the lowest of
     * maxPerOption, max and the API's 99. Outside the quantity control, 1.
     */
    public static function unitCap(array $rule): int
    {
        if ($rule['control'] !== 'quantity') {
            return 1;
        }

        return min($rule['maxPerOption'] ?? self::MAX_QUANTITY, $rule['max'] ?? self::MAX_QUANTITY, self::MAX_QUANTITY);
    }

    /** `max: 0` is valid and means the group takes no option: painted read-only. */
    public static function isInert(array $rule): bool
    {
        return $rule['max'] === 0;
    }

    /**
     * How much to choose, in one line, with the panel's own wording. Only the MAGNITUDE
     * branches by control: options in radio/checkbox, units in quantity.
     */
    public static function ruleLabel(array $rule): string
    {
        ['min' => $min, 'max' => $max] = $rule;

        if ($rule['control'] === 'radio') {
            return 'Selecciona 1';
        }

        if (self::isInert($rule)) {
            return self::INERT_LABEL;
        }

        if ($min >= 1 && $max !== null) {
            return $min === $max
                ? 'Selecciona '.self::amount($rule, $min)
                : "Selecciona entre {$min} y ".self::amount($rule, $max);
        }

        if ($min >= 1) {
            return 'Selecciona al menos '.self::amount($rule, $min);
        }

        if ($max !== null) {
            return 'Hasta '.self::amount($rule, $max);
        }

        return self::OPTIONAL_LABEL;
    }

    /** The number with its unit when it isn't implied: "unidades" only in quantity. */
    private static function amount(array $rule, int $count): string
    {
        if ($rule['control'] !== 'quantity') {
            return (string) $count;
        }

        return $count.' '.($count === 1 ? 'unidad' : 'unidades');
    }
}
