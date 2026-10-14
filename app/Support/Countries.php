<?php

namespace App\Support;

/**
 * The countries of the phone code selector. Port of src/data/countries.ts: extend it
 * here and in App\View\Components\Flag (both are needed: data and flag).
 */
final class Countries
{
    /** @var list<array{code: string, name: string, dialCode: string}> */
    public const LIST = [
        ['code' => 'mx', 'name' => 'Mexico', 'dialCode' => '+52'],
        ['code' => 'us', 'name' => 'Estados Unidos', 'dialCode' => '+1'],
        ['code' => 'br', 'name' => 'Brasil', 'dialCode' => '+55'],
        ['code' => 'pe', 'name' => 'Peru', 'dialCode' => '+51'],
        ['code' => 'ar', 'name' => 'Argentina', 'dialCode' => '+54'],
        ['code' => 'co', 'name' => 'Colombia', 'dialCode' => '+57'],
    ];

    /** @return array{code: string, name: string, dialCode: string} */
    public static function default(): array
    {
        return self::LIST[0];
    }
}
