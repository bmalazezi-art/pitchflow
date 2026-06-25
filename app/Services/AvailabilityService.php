<?php

namespace App\Services;

use App\Models\FootballField;
use App\Models\OperatingHourOverride;
use App\Support\Timezones;
use Carbon\CarbonImmutable;

class AvailabilityService
{
    public function slots(FootballField $field, string $date): array
    {
        $organization = $field->organization;
        $timezone = Timezones::resolve($organization->timezone);
        $localDate = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $override = $field->hasOne(OperatingHourOverride::class)->whereDate('date', $localDate)->first();
        $hours = $field->operatingHours()->where('day_of_week', $localDate->dayOfWeek)->first();

        if ($override?->is_closed || (! $override && $hours?->is_closed)) {
            return [];
        }

        $opening = $override?->opening_time ?? $hours?->opening_time ?? $field->opening_time;
        $closing = $override?->closing_time ?? $hours?->closing_time ?? $field->closing_time;
        $cursor = CarbonImmutable::parse($localDate->format('Y-m-d').' '.$opening, $timezone);
        $end = CarbonImmutable::parse($localDate->format('Y-m-d').' '.$closing, $timezone);
        if ($end->lessThanOrEqualTo($cursor)) {
            $end = $end->addDay();
        }

        $occupied = $field->reservations()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('starts_at', '<', $end->utc())
            ->where('ends_at', '>', $cursor->utc())
            ->get(['starts_at', 'ends_at']);

        $slots = [];
        while ($cursor->lessThan($end)) {
            $slotEnd = $cursor->addHour();
            $isOccupied = $occupied->contains(fn ($reservation) => $reservation->starts_at->lt($slotEnd->utc())
                && $reservation->ends_at->gt($cursor->utc()));
            $slots[] = [
                'starts_at' => $cursor->format('Y-m-d\TH:i'),
                'ends_at' => $slotEnd->format('Y-m-d\TH:i'),
                'label' => $cursor->format('H:i').'–'.$slotEnd->format('H:i'),
                'status' => $cursor->isPast() ? 'past' : ($isOccupied ? 'occupied' : 'available'),
            ];
            $cursor = $slotEnd;
        }

        return $slots;
    }
}
