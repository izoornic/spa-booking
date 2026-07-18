<?php

namespace Tests\Unit;

use App\Models\SpaConfig;
use Tests\TestCase;

class SpaConfigSlotsTest extends TestCase
{
    public function test_generates_three_fixed_three_hour_slots(): void
    {
        $config = new SpaConfig([
            'radno_od' => '12:00',
            'radno_do' => '21:00',
            'broj_slotova' => 3,
        ]);

        $slots = $config->slots();

        $this->assertCount(3, $slots);
        $this->assertSame(['start' => '12:00', 'end' => '15:00'], $slots[1]);
        $this->assertSame(['start' => '15:00', 'end' => '18:00'], $slots[2]);
        $this->assertSame(['start' => '18:00', 'end' => '21:00'], $slots[3]);
    }
}
