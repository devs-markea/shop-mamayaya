<?php

namespace Tests\Feature\Support;

use App\Support\Options;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OptionsTest extends TestCase
{
    private static function rule(string $control, int $min, ?int $max, ?int $maxPerOption = null): array
    {
        return compact('control', 'min', 'max', 'maxPerOption');
    }

    public static function labels(): array
    {
        return [
            [self::rule('radio', 1, 1), 'Selecciona 1'],
            [self::rule('checkbox', 2, 2), 'Selecciona 2'],
            [self::rule('checkbox', 1, 3), 'Selecciona entre 1 y 3'],
            [self::rule('checkbox', 2, null), 'Selecciona al menos 2'],
            [self::rule('checkbox', 0, 2), 'Hasta 2'],
            [self::rule('checkbox', 0, null), 'Opcional'],
            [self::rule('checkbox', 0, 0), 'No se puede elegir ninguna opcion'],
            [self::rule('quantity', 8, 8, 4), 'Selecciona 8 unidades'],
            [self::rule('quantity', 0, 1), 'Hasta 1 unidad'],
        ];
    }

    #[Test]
    #[DataProvider('labels')]
    public function rotula_cada_grupo_como_el_panel(array $rule, string $label): void
    {
        $this->assertSame($label, Options::ruleLabel($rule));
    }

    #[Test]
    public function el_techo_de_una_opcion_es_el_mas_bajo(): void
    {
        $this->assertSame(1, Options::unitCap(self::rule('checkbox', 0, 5)));
        $this->assertSame(4, Options::unitCap(self::rule('quantity', 0, 8, 4)));
        $this->assertSame(3, Options::unitCap(self::rule('quantity', 0, 3, 4)));
        $this->assertSame(99, Options::unitCap(self::rule('quantity', 0, null)));
    }
}
