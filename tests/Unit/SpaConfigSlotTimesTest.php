<?php

namespace Tests\Unit;

use App\Models\SpaConfig;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SpaConfigSlotTimesTest extends TestCase
{
    public function test_slot_start_and_end_datetimes(): void
    {
        $config = new SpaConfig(['raspored' => SpaConfig::podrazumevaniRaspored()]);

        $datum = CarbonImmutable::parse('2026-08-03');

        $this->assertSame('2026-08-03 15:00', $config->slotStartAt($datum, 2)->format('Y-m-d H:i'));
        $this->assertSame('2026-08-03 18:00', $config->slotEndAt($datum, 2)->format('Y-m-d H:i'));
    }

    public function test_slot_times_follow_the_weekday_schedule(): void
    {
        $config = new SpaConfig(['raspored' => [
            1 => [['start' => '12:00', 'end' => '15:00']],
            6 => [['start' => '09:30', 'end' => '11:00']],
        ]]);

        $subota = CarbonImmutable::parse('2026-08-08');

        $this->assertSame('2026-08-08 09:30', $config->slotStartAt($subota, 1)->format('Y-m-d H:i'));
        $this->assertSame('2026-08-08 11:00', $config->slotEndAt($subota, 1)->format('Y-m-d H:i'));
        $this->assertSame('09:30–11:00', $config->slotOpis($subota, 1));
    }

    public function test_invalid_slot_index_throws(): void
    {
        $config = new SpaConfig(['raspored' => SpaConfig::podrazumevaniRaspored()]);

        $this->expectException(\InvalidArgumentException::class);
        $config->slotWindow(CarbonImmutable::parse('2026-08-03'), 4);
    }

    public function test_slot_missing_from_schedule_reads_as_finished_and_keeps_a_label(): void
    {
        // A past reservation made under an older schedule whose termin no longer exists.
        $config = new SpaConfig(['raspored' => [1 => [['start' => '12:00', 'end' => '15:00']]]]);
        $datum = CarbonImmutable::parse('2026-08-03');

        $this->assertSame('2026-08-03 00:00', $config->slotStartAt($datum, 3)->format('Y-m-d H:i'));
        $this->assertSame('2026-08-03 00:00', $config->slotEndAt($datum, 3)->format('Y-m-d H:i'));
        $this->assertSame('3. termin', $config->slotOpis($datum, 3));
    }
}
