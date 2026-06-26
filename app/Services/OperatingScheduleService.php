<?php

namespace App\Services;

use App\Models\FootballField;
use App\Support\Timezones;
use Carbon\CarbonImmutable;

class OperatingScheduleService
{
    public function hoursForDate(FootballField $field, CarbonImmutable $businessDate): float
    {
        [$scheduleStart, $scheduleEnd] = $this->windowForDate($field, $businessDate);

        return $scheduleStart && $scheduleEnd
            ? $scheduleStart->diffInMinutes($scheduleEnd) / 60
            : 0;
    }

    public function contains(FootballField $field, CarbonImmutable $startsAtUtc, CarbonImmutable $endsAtUtc): bool
    {
        $timezone = Timezones::resolve($field->organization->timezone);
        $localStart = $startsAtUtc->setTimezone($timezone);
        $localEnd = $endsAtUtc->setTimezone($timezone);

        foreach ([$localStart->startOfDay(), $localStart->subDay()->startOfDay()] as $businessDate) {
            [$scheduleStart, $scheduleEnd] = $this->windowForDate($field, $businessDate);
            if (
                $scheduleStart !== null
                && $scheduleEnd !== null
                && $localStart->greaterThanOrEqualTo($scheduleStart)
                && $localEnd->lessThanOrEqualTo($scheduleEnd)
            ) {
                return true;
            }
        }

        return false;
    }

    private function windowForDate(FootballField $field, CarbonImmutable $businessDate): array
    {
        $timezone = Timezones::resolve($field->organization->timezone);
        $override = $field->relationLoaded('operatingHourOverrides')
            ? $field->operatingHourOverrides->first(fn ($item) => $item->date->isSameDay($businessDate))
            : $field->operatingHourOverrides()->whereDate('date', $businessDate)->first();
        $hours = $field->relationLoaded('operatingHours')
            ? $field->operatingHours->firstWhere('day_of_week', $businessDate->dayOfWeek)
            : $field->operatingHours()->where('day_of_week', $businessDate->dayOfWeek)->first();

        if ($override?->is_closed || (! $override && $hours?->is_closed)) {
            return [null, null];
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
        $scheduleStart = CarbonImmutable::parse($businessDate->format('Y-m-d').' '.$opening, $timezone);
        $scheduleEnd = CarbonImmutable::parse($businessDate->format('Y-m-d').' '.$closing, $timezone);
        if ($scheduleEnd->lessThanOrEqualTo($scheduleStart)) {
            $scheduleEnd = $scheduleEnd->addDay();
        }

        return [$scheduleStart, $scheduleEnd];
    }
}
