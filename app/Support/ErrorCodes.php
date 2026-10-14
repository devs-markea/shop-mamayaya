<?php

namespace App\Support;

use App\Services\ShopApi\ShopApiException;
use Throwable;

/**
 * The short code shown next to every failure notice (`SF-M202`, `SF-DNET`) and written
 * to the log with the same text, so a buyer's screenshot can be searched literally.
 *
 * Port of resources/js/lib/error-codes.ts, which the browser keeps using: both sides
 * must compose codes the same way. The catalog of codes lives outside the repository,
 * in `SuperFoods/feature/codigos-de-error-visibles.md`.
 */
final class ErrorCodes
{
    /** Menu: the home page. */
    public const MENU = 'M';

    /** Product page. */
    public const DETAIL = 'D';

    public const CART = 'C';

    /** Shipping quote on /mamayaya/datos. */
    public const SHIPPING = 'E';

    /** The payment screens, checkout and charge. */
    public const PAYMENT = 'P';

    /** Acknowledgement and order reread. */
    public const RECEIPT = 'R';

    /** The code of a failed server-side read. Never throws and never returns empty. */
    public static function fromException(string $area, Throwable $error): string
    {
        if (! $error instanceof ShopApiException) {
            return self::compose($area, 'ERR');
        }

        if ($error->status > 0) {
            return self::compose($area, (string) $error->status);
        }

        return self::compose($area, $error->timedOut ? 'TMO' : 'NET');
    }

    /** How it is ALWAYS written, here and in the notice: "Codigo SF-M202". */
    public static function label(string $code): string
    {
        return "Codigo {$code}";
    }

    private static function compose(string $area, string $cause): string
    {
        return "SF-{$area}{$cause}";
    }
}
