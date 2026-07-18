<?php

namespace Tests\Unit;

use App\Models\SpaConfig;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SpaConfigSlotTimesTest extends TestCase
{
    public function test_slot_start_and_end_datetimes(): void
    {
        $config = new SpaConfig([
            'radno_od' => '12:00',
            'radno_do' => '21:00',
            'broj_slotova' => 3,
        ]);

        $datum = CarbonImmutable::parse('2026-08-03');

        $this->assertSame('2026-08-03 15:00', $config->slotStartAt($datum, 2)->format('Y-m-d H:i'));
        $this->assertSame('2026-08-03 18:00', $config->slotEndAt($datum, 2)->format('Y-m-d H:i'));
    }

    public function test_invalid_slot_index_throws(): void
    {
        $config = new SpaConfig([
            'radno_od' => '12:00',
            'radno_do' => '21:00',
            'broj_slotova' => 3,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $config->slotWindow(4);
    }
}
