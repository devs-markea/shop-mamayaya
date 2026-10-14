<?php

namespace App\Support;

/**
 * Prices in MXN, written the way the browser writes them.
 *
 * It must match `Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' })`
 * (resources/js/lib/price.ts) to the character: the cart repaints totals in the
 * browser, and a server-rendered "$1,234.50" next to a client-rendered "$1 234,50"
 * would read as two different amounts.
 */
final class Price
{
    public static function format(float|int $value): string
    {
        $formatted = '$'.number_format(abs((float) $value), 2, '.', ',');

        return round((float) $value, 2) < 0 ? "-{$formatted}" : $formatted;
    }
}
