<?php

namespace App\Support;

/**
 * The CLABE (the 18-digit interbank account number) as it's read on screen. Port of
 * src/lib/clabe.ts.
 */
final class Clabe
{
    /**
     * Grouped 4-4-4-6. It's stored and copied raw (what online banking accepts) and only
     * grouped when painted; anything that isn't a digit is dropped first.
     */
    public static function format(string $clabe): string
    {
        $digits = preg_replace('/\D/', '', $clabe);

        $groups = [substr($digits, 0, 4), substr($digits, 4, 4), substr($digits, 8, 4), substr($digits, 12)];

        return implode(' ', array_filter($groups, fn (string $group) => $group !== ''));
    }
}
