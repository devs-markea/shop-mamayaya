<?php

namespace App\Support;

/**
 * The API's 429, written for the buyer. Port of resources/js/lib/throttle.ts.
 */
final class Throttle
{
    /**
     * The 429 of the READ screens (catalog, product, settings).
     *
     * Not the buyer's fault: the read limit is counted per IP, and every buyer reaches the
     * API through this server's IP, so the bucket is shared. The text says nothing broke
     * and how long it takes, and does not invite a blind reload.
     */
    public static function busyMessage(?int $seconds): string
    {
        $wait = $seconds === null ? 'un momento' : self::humanize($seconds);

        return "La tienda esta recibiendo muchas visitas. El menu vuelve en {$wait}.";
    }

    /** "un momento" / "15 segundos" / "2 minutos". */
    private static function humanize(int $seconds): string
    {
        if ($seconds < 10) {
            return 'un momento';
        }

        if ($seconds < 60) {
            return "{$seconds} segundos";
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes === 1 ? 'un minuto' : "{$minutes} minutos";
    }
}
