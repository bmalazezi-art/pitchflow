<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\FootballField;
use App\Support\Timezones;
use Carbon\CarbonImmutable;

class AvailabilityService
{
    public function __construct(private readonly SlotStatusService $slotStatuses) {}

    public function slots(FootballField $field, string $date, CarbonImmutable|string|null $now = null): array
    {
        $organization = $field->organization;
        $timezone = Timezones::resolve($organization->timezone);
        $localDate = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $override = $field->operatingHourOverrides()->whereDate('date', $localDate)->first();
        $hours = $field->operatingHours()->where('day_of_week', $localDate->dayOfWeek)->first();

        if ($override?->is_closed || (! $override && $hours?->is_closed)) {
            return [];
        }

        $opening = $field->opening_time;
        $closing = $field->closing_time;
        if ($hours !== null) {
            $opening = $hours->opening_time;
            $closing = $hours->closing_time;
        }
        if ($override !== null) {
            $opening = $override->opening_time ?? $opening;
            $closing = $override->closing_time ?? $closing;
        }
        $cursor = CarbonImmutable::parse($localDate->format('Y-m-d').' '.$opening, $timezone);
        $end = CarbonImmutable::parse($localDate->format('Y-m-d').' '.$closing, $timezone);
        if ($end->lessThanOrEqualTo($cursor)) {
            $end = $end->addDay();
        }

        $occupied = $field->reservations()
            ->whereIn('status', ReservationStatus::blockedValues())
            ->where('starts_at', '<', $end->utc())
            ->where('ends_at', '>', $cursor->utc())
            ->get(['id', 'starts_at', 'ends_at', 'status']);

        $slots = [];
        while ($cursor->lessThan($end)) {
            $slotEnd = $cursor->addHour();
            $reservation = $occupied->first(fn ($reservation) => $reservation->starts_at->lt($slotEnd->utc())
                && $reservation->ends_at->gt($cursor->utc()));
            $status = $this->slotStatuses->getSlotStatus(
                $cursor->toDateString(),
                $cursor->format('H:i'),
                $slotEnd->format('H:i'),
                $reservation?->status?->value,
                $timezone,
                $now,
            );
            $slots[] = [
                'starts_at' => $cursor->format('Y-m-d\TH:i'),
                'ends_at' => $slotEnd->format('Y-m-d\TH:i'),
                'label' => $cursor->format('H:i').'–'.$slotEnd->format('H:i'),
                'status' => $status,
                'reservation_id' => $reservation?->id,
                'timezone' => $timezone,
            ];
            $cursor = $slotEnd;
        }

        return $slots;
    }
}
