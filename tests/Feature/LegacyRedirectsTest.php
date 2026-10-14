<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyRedirectsTest extends TestCase
{
    public static function legacyPaths(): array
    {
        return [
            ['/', '/mamayaya'],
            ['/carrito', '/mamayaya/carrito'],
            ['/datos', '/mamayaya/datos'],
            ['/pago', '/mamayaya/pago'],
            ['/pago/transferencia', '/mamayaya/pago/transferencia'],
            ['/pago/efectivo', '/mamayaya/pago/efectivo'],
            ['/recibido', '/mamayaya/recibido'],
            ['/confirmado', '/mamayaya/recibido'],
            ['/mamayaya/confirmado', '/mamayaya/recibido'],
        ];
    }

    #[Test]
    #[DataProvider('legacyPaths')]
    public function redirige_con_302_a_su_pantalla(string $from, string $to): void
    {
        $this->get($from)->assertStatus(302)->assertRedirect($to);
    }

    #[Test]
    public function conserva_la_query_de_la_vuelta_de_mercado_pago(): void
    {
        $this->get('/mamayaya/confirmado?order=01JABCDEF')
            ->assertStatus(302)
            ->assertRedirect('/mamayaya/recibido?order=01JABCDEF');
    }
}
