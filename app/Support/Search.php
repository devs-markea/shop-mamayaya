<?php

namespace App\Support;

use Normalizer;

/**
 * The menu search normalization, server side.
 *
 * The server writes each dish's normalized text into `data-search` and the browser
 * normalizes what the buyer types with normalizeSearch() (resources/js/lib/search.ts).
 * Both must produce the same string, or "Jamón" would stop finding "jamon": lowercase,
 * no accents or diaeresis, whitespace collapsed to one space.
 */
final class Search
{
    public static function normalize(string $text): string
    {
        if (class_exists(Normalizer::class)) {
            $decomposed = Normalizer::normalize($text, Normalizer::FORM_D);
            $text = preg_replace('/\p{M}/u', '', $decomposed === false ? $text : $decomposed) ?? $text;
        } else {
            // Without ext-intl, transliterate the usual Spanish letters by hand.
            $text = strtr($text, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
                'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
                'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            ]);
        }

        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($text)) ?? '');
    }
}
