<?php

namespace Tests\Feature\Support;

use App\Support\Schedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    private const NBSP = "\u{00A0}";

    /** A Wednesday in Cancún (UTC-5, no DST). */
    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-10-14 {$time}", 'America/Cancun');
    }

    /** @param  array<int, list<array{0: string, 1: string}>>  $shifts  dayOfWeek => [[start, end], ...] */
    private function days(array $shifts): array
    {
        return array_map(fn (int $day) => [
            'dayOfWeek' => $day,
            'name' => "Dia {$day}",
            'shifts' => array_map(fn ($s) => ['start' => $s[0], 'end' => $s[1]], $shifts[$day] ?? []),
        ], range(1, 7));
    }

    #[Test]
    public function abre_al_inicio_y_cierra_al_final_del_rango(): void
    {
        $days = $this->days([3 => [['12:00', '23:30']]]);

        $this->assertFalse(Schedule::resolve($days, $this->at('11:59'))['isOpen']);
        $this->assertTrue(Schedule::resolve($days, $this->at('12:00'))['isOpen']);
        $this->assertSame('23:30', Schedule::resolve($days, $this->at('23:29'))['closesAt']);
        $this->assertFalse(Schedule::resolve($days, $this->at('23:30'))['isOpen']);
    }

    #[Test]
    public function el_turno_que_cruza_medianoche_sigue_al_dia_siguiente(): void
    {
        $days = $this->days([2 => [['22:00', '02:00']]]);

        $this->assertTrue(Schedule::resolve($days, $this->at('01:30'))['isOpen']);
        $this->assertFalse(Schedule::resolve($days, $this->at('02:00'))['isOpen']);
    }

    #[Test]
    public function cerrada_dice_cuando_vuelve(): void
    {
        $days = $this->days([3 => [['18:00', '23:00']], 4 => [['09:00', '23:00']]]);

        $today = Schedule::resolve($days, $this->at('15:00'));
        $this->assertSame(['dayOfWeek' => 3, 'time' => '18:00', 'inMinutes' => 180], $today['opensAt']);

        $tomorrow = Schedule::resolve($days, $this->at('23:10'));
        $this->assertSame('09:00', $tomorrow['opensAt']['time']);
        $this->assertSame('manana', Schedule::nextOpeningDay([...$tomorrow, 'days' => $days], $this->at('23:10')));
        $this->assertSame('closed-today', Schedule::status([...$tomorrow, 'days' => $days], $this->at('23:10')));
        $this->assertSame('closed-now', Schedule::status([...$today, 'days' => $days], $this->at('15:00')));
    }

    #[Test]
    public function sin_rangos_no_se_afirma_nada(): void
    {
        $this->assertNull(Schedule::resolve($this->days([]))['isOpen']);
        $this->assertNull(Schedule::resolve([['name' => 'Lunes a viernes', 'shifts' => []]]));
    }

    #[Test]
    public function rotula_el_turno_en_curso_con_espacio_duro(): void
    {
        $days = $this->days([3 => [['09:00', '14:00'], ['18:00', '23:00']]]);
        $open = ['isOpen' => true, 'closesAt' => '23:00', 'opensAt' => null, 'days' => $days];

        $this->assertSame('18:00 - 23:00'.self::NBSP.'h.', Schedule::shiftLabel($open, $this->at('19:00')));

        $closed = ['isOpen' => false, 'closesAt' => null, 'opensAt' => ['dayOfWeek' => 3, 'time' => '18:00', 'inMinutes' => 60], 'days' => $days];

        $this->assertSame('Cerrado · Abre hoy a las 18:00'.self::NBSP.'h.', Schedule::shiftLabel($closed, $this->at('17:00')));
    }

    #[Test]
    public function el_turno_continuo_es_de_24_horas(): void
    {
        $days = $this->days([3 => [['00:00', '00:00']]]);
        $open = ['isOpen' => true, 'closesAt' => null, 'opensAt' => null, 'days' => $days];

        $this->assertNull(Schedule::resolve($days, $this->at('10:00'))['closesAt']);
        $this->assertSame('Abierto las 24 horas', Schedule::shiftLabel($open, $this->at('10:00')));
        $this->assertSame('24 horas', Schedule::formatShifts($days[2]));
    }

    #[Test]
    public function formatea_los_turnos_de_un_dia(): void
    {
        $day = ['name' => 'Viernes', 'shifts' => [['start' => '09:00', 'end' => '14:00'], ['start' => '22:00', 'end' => '02:00', 'crossesMidnight' => true]]];

        $this->assertSame('09:00 a 14:00'.self::NBSP.'h. · 22:00 a 02:00'.self::NBSP.'h. +1 dia', Schedule::formatShifts($day));
    }

    #[Test]
    public function normaliza_el_contrato_anterior(): void
    {
        $schedule = Schedule::normalize(['isOpen' => true, 'label' => 'Abierto', 'hours' => [['days' => 'Lunes a viernes', 'shifts' => ['09:00-23:00']]]]);

        $this->assertSame([['name' => 'Lunes a viernes', 'shifts' => [['start' => '09:00', 'end' => '23:00']]]], $schedule['days']);
        $this->assertSame('Abierto', Schedule::label($schedule));
    }

    #[Test]
    public function compara_el_calculo_con_el_servidor(): void
    {
        $days = $this->days([3 => [['12:00', '23:00']]]);
        $resolved = Schedule::resolve($days, $this->at('13:00'));

        $this->assertNull(Schedule::mismatch(['isOpen' => true, 'closesAt' => '23:00', 'opensAt' => null], $resolved));
        $this->assertStringStartsWith('closesAt', Schedule::mismatch(['isOpen' => true, 'closesAt' => '22:00', 'opensAt' => null], $resolved));
    }

    #[Test]
    public function el_ano_es_el_de_la_tienda(): void
    {
        // 03:00 UTC on New Year's Day is still December 31st in Cancún.
        $this->assertSame(2026, Schedule::storeYear(CarbonImmutable::parse('2027-01-01 03:00', 'UTC')));
    }
}
