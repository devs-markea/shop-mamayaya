<?php

namespace App\Support;

/**
 * The tip: which amounts are offered and which one is accepted. Port of
 * resources/js/lib/tips.ts, which the browser keeps using for the "Otro" field: both
 * have to paint the same buttons and accept the same amounts.
 *
 * The API validates very little (`tip` only has to be numeric and >= 0), so what a
 * reasonable tip is gets decided here. The single amount rule lives in
 * CheckoutDraft::parseTipAmount(), which also reads the draft cookie.
 */
final class Tips
{
    /** Digits that fit in the "Otro" field: its `maxlength` and the cap are the same rule. */
    public const MAX_DIGITS = 5;

    /**
     * The panel's amounts, ready to be painted as buttons: valid integers only, without
     * duplicates, sorted, and with the 0 in front ("no tip" belongs to the buyer, not to
     * the store's configuration). Without any positive amount, an empty list: a selector
     * whose only button is "nothing" isn't a selector.
     *
     * @return list<int>
     */
    public static function normalizeAmounts(mixed $amounts): array
    {
        if (! is_array($amounts) || ! array_is_list($amounts)) {
            return [];
        }

        $positives = [];

        foreach ($amounts as $amount) {
            $amount = CheckoutDraft::parseTipAmount($amount);

            if ($amount !== null && $amount > 0) {
                $positives[$amount] = $amount;
            }
        }

        sort($positives);

        return $positives === [] ? [] : [0, ...$positives];
    }

    /** Whether the chosen amount was typed in "Otro" instead of pressed. */
    public static function isCustom(int $tip, array $amounts): bool
    {
        return $tip > 0 && ! in_array($tip, $amounts, true);
    }
}
