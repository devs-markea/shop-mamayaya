<?php

namespace Tests\Feature\Services;

use App\Services\Theme\ThemeRepository;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThemeRepositoryTest extends TestCase
{
    #[Test]
    public function lee_los_temas_de_la_carpeta(): void
    {
        $themes = $this->app->make(ThemeRepository::class)->all();

        $this->assertEqualsCanonicalizing(['gold', 'green', 'orange', 'pink'], array_keys($themes));
    }

    #[Test]
    public function escribe_el_acento_como_tres_numeros(): void
    {
        $theme = $this->app->make(ThemeRepository::class)->get('gold');

        $this->assertSame(
            '--sf-accent-rgb: 213, 170, 83; --sf-accent-hover: #c49a45; --sf-accent-bright: #f5a623',
            $theme->style(),
        );
    }

    #[Test]
    public function un_tema_desconocido_cae_al_de_por_defecto(): void
    {
        $this->assertSame('gold', $this->app->make(ThemeRepository::class)->get('violeta')->id);
    }
}
