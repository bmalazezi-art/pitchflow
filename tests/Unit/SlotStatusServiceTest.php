<?php

namespace Tests\Unit;

use App\Services\SlotStatusService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SlotStatusServiceTest extends TestCase
{
    public function test_it_calculates_past_current_future_and_reserved_slots_with_full_datetimes(): void
    {
        $service = new SlotStatusService;
        $now = CarbonImmutable::parse('2026-07-26 14:34', 'Europe/Belgrade');

        $this->assertSame('past', $service->getSlotStatus('2026-07-26', '12:00', '13:00', null, 'Europe/Belgrade', $now));
        $this->assertSame('current', $service->getSlotStatus('2026-07-26', '14:00', '15:00', null, 'Europe/Belgrade', $now));
        $this->assertSame('available', $service->getSlotStatus('2026-07-26', '15:00', '16:00', null, 'Europe/Belgrade', $now));
        $this->assertSame('reserved', $service->getSlotStatus('2026-07-26', '16:00', '17:00', 'confirmed', 'Europe/Belgrade', $now));
        $this->assertSame('reserved', $service->getSlotStatus('2026-07-26', '17:00', '18:00', 'completed', 'Europe/Belgrade', $now));
    }

    public function test_it_handles_overnight_slot_end_times(): void
    {
        $service = new SlotStatusService;
        $now = CarbonImmutable::parse('2026-07-26 14:34', 'Europe/Belgrade');

        $this->assertSame('available', $service->getSlotStatus('2026-07-26', '23:00', '00:00', null, 'Europe/Belgrade', $now));
    }
}
