<?php

namespace Tests\Unit;

use App\Models\SpaConfig;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SpaConfigSlotsTest extends TestCase
{
    public function test_default_schedule_has_three_three_hour_slots_every_day(): void
    {
        $config = new SpaConfig(['raspored' => SpaConfig::podrazumevaniRaspored()]);

        foreach (array_keys(SpaConfig::DANI) as $dan) {
            $slots = $config->slotsForWeekday($dan);

            $this->assertCount(3, $slots);
            $this->assertSame(['start' => '12:00', 'end' => '15:00'], $slots[1]);
            $this->assertSame(['start' => '15:00', 'end' => '18:00'], $slots[2]);
            $this->assertSame(['start' => '18:00', 'end' => '21:00'], $slots[3]);
        }
    }

    public function test_each_weekday_has_its_own_termini_sorted_by_start(): void
    {
        $config = new SpaConfig(['raspored' => [
            1 => [['start' => '16:00', 'end' => '20:00']],
            6 => [
                ['start' => '14:00', 'end' => '17:00'],
                ['start' => '10:00', 'end' => '13:00'],
            ],
            7 => [],
        ]]);

        $this->assertSame([1 => ['start' => '16:00', 'end' => '20:00']], $config->slotsForWeekday(1));
        $this->assertSame([
            1 => ['start' => '10:00', 'end' => '13:00'],
            2 => ['start' => '14:00', 'end' => '17:00'],
        ], $config->slotsForWeekday(6));
        $this->assertSame([], $config->slotsForWeekday(7));
        $this->assertSame([], $config->slotsForWeekday(3), 'A day missing from the schedule is closed');

        // 2026-08-08 is a Saturday.
        $this->assertSame($config->slotsForWeekday(6), $config->slotsFor(CarbonImmutable::parse('2026-08-08')));
        $this->assertTrue($config->hasSlot(CarbonImmutable::parse('2026-08-08'), 2));
        $this->assertFalse($config->hasSlot(CarbonImmutable::parse('2026-08-09'), 1));
    }

    public function test_schedule_groups_consecutive_days_with_same_termini(): void
    {
        $radni = [['start' => '16:00', 'end' => '21:00']];
        $vikend = [['start' => '10:00', 'end' => '13:00'], ['start' => '13:00', 'end' => '16:00']];

        $config = new SpaConfig(['raspored' => [
            1 => $radni, 2 => $radni, 3 => $radni, 4 => $radni, 5 => $radni,
            6 => $vikend, 7 => [],
        ]]);

        $this->assertSame([
            ['dani' => 'Ponedeljak – Petak', 'termini' => ['16:00–21:00']],
            ['dani' => 'Subota', 'termini' => ['10:00–13:00', '13:00–16:00']],
            ['dani' => 'Nedelja', 'termini' => []],
        ], $config->rasporedPoGrupama());

        $svakiDan = new SpaConfig(['raspored' => SpaConfig::podrazumevaniRaspored()]);
        $this->assertSame('Svaki dan', $svakiDan->rasporedPoGrupama()[0]['dani']);
        $this->assertCount(1, $svakiDan->rasporedPoGrupama());
    }
}
