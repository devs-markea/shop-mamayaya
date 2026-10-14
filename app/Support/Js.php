<?php

namespace App\Support;

/**
 * JavaScript's string helpers, for markup the server and the browser must write
 * identically.
 */
final class Js
{
    /** encodeURIComponent(): rawurlencode() but leaving `!'()*` alone. */
    public static function encodeURIComponent(string $value): string
    {
        return strtr(rawurlencode($value), ['%21' => '!', '%27' => "'", '%28' => '(', '%29' => ')', '%2A' => '*']);
    }

    /**
     * The escape() of resources/js/lib/cart-view.ts: the five HTML characters, with the
     * apostrophe as `&#39;` (htmlspecialchars() would write `&#039;`).
     */
    public static function escapeHtml(string $value): string
    {
        return strtr($value, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;', '"' => '&quot;', "'" => '&#39;']);
    }
}
