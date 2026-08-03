<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Support\Timezones;
use Carbon\CarbonImmutable;

class SlotStatusService
{
    public function isSlotBlockedByReservation(?string $status): bool
    {
        return in_array($status, [...ReservationStatus::blockedValues(), 'reserved', 'occupied'], true);
    }

    public function getSlotStatus(
        string $selectedDate,
        string $startTime,
        string $endTime,
        ?string $reservationStatus,
        string $timezone = 'Europe/Belgrade',
        CarbonImmutable|string|null $now = null,
    ): string {
        $timezone = Timezones::resolve($timezone);
        $slotStart = CarbonImmutable::parse($selectedDate.' '.$startTime, $timezone);
        $slotEnd = CarbonImmutable::parse($selectedDate.' '.$endTime, $timezone);

        if ($slotEnd->lessThanOrEqualTo($slotStart)) {
            $slotEnd = $slotEnd->addDay();
        }

        $localNow = $now instanceof CarbonImmutable
            ? $now->setTimezone($timezone)
            : ($now ? CarbonImmutable::parse($now, $timezone) : CarbonImmutable::now($timezone));

        if ($reservationStatus === 'closed') {
            return 'closed';
        }

        if ($slotEnd->lessThanOrEqualTo($localNow)) {
            return 'past';
        }

        if ($slotStart->lessThanOrEqualTo($localNow) && $slotEnd->greaterThan($localNow)) {
            return 'current';
        }

        return $this->isSlotBlockedByReservation($reservationStatus)
            ? 'reserved'
            : 'available';
    }
}
