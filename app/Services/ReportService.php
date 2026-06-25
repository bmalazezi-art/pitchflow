<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Organization;
use App\Models\Reservation;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportService
{
    public function dashboard(Organization $organization): array
    {
        $timezone = Timezones::resolve($organization->timezone);
        $now = CarbonImmutable::now($timezone);
        $todayStart = $now->startOfDay()->utc();
        $todayEnd = $now->endOfDay()->utc();
        $monthStart = $now->startOfMonth()->utc();
        $monthEnd = $now->endOfMonth()->utc();

        $base = Reservation::query()->forOrganization($organization->id);
        $today = (clone $base)->whereBetween('starts_at', [$todayStart, $todayEnd]);
        $month = (clone $base)->whereBetween('starts_at', [$monthStart, $monthEnd]);

        $fieldCount = max(1, $organization->footballFields()->where('status', 'active')->count());
        $elapsedHours = max(1, $now->startOfDay()->diffInHours($now));
        $occupiedHours = (clone $today)
            ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value, ReservationStatus::Completed->value])
            ->get(['starts_at', 'ends_at'])
            ->sum(fn (Reservation $reservation) => $reservation->starts_at->diffInMinutes($reservation->ends_at) / 60);

        return [
            'today_reservations' => (clone $today)->count(),
            'today_revenue' => (float) (clone $today)->where('payment_status', PaymentStatus::Paid->value)->sum('paid_amount'),
            'monthly_revenue' => (float) (clone $month)->where('payment_status', PaymentStatus::Paid->value)->sum('paid_amount'),
            'occupancy_rate' => round(min(100, ($occupiedHours / ($fieldCount * $elapsedHours)) * 100), 1),
            'upcoming' => (clone $base)->with('footballField:id,name')->where('starts_at', '>=', now())
                ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
                ->orderBy('starts_at')->limit(6)->get(),
            'weekly' => $this->weeklyCounts($organization, $now),
            'active_employees' => $organization->users()->where('role', 'employee')->count(),
        ];
    }

    public function detailed(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $reservations = Reservation::query()
            ->forOrganization($organization->id)
            ->with('footballField:id,name')
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->get();

        $totalHours = $reservations->sum(fn (Reservation $reservation) => $reservation->starts_at->diffInMinutes($reservation->ends_at) / 60);
        $availableHours = max(1, $organization->footballFields()->count() * max(1, $from->diffInDays($to) + 1) * 13);

        return [
            'reservation_count' => $reservations->count(),
            'collected_revenue' => (float) $reservations->where('payment_status', PaymentStatus::Paid)->sum('paid_amount'),
            'booked_revenue' => (float) $reservations->sum('price'),
            'occupancy_rate' => round(min(100, ($totalHours / $availableHours) * 100), 1),
            'walk_ins' => $reservations->where('is_walk_in', true)->count(),
            'no_shows' => $reservations->where('status', ReservationStatus::NoShow)->count(),
            'late_cancellations' => $reservations->where('status', ReservationStatus::LateCancelled)->count(),
            'most_booked_field' => $this->mostBookedField($reservations),
            'peak_hours' => $reservations->groupBy(fn (Reservation $reservation) => $reservation->starts_at->setTimezone(Timezones::resolve($organization->timezone))->format('H:00'))
                ->map->count()->sortDesc()->take(8),
        ];
    }

    private function weeklyCounts(Organization $organization, CarbonImmutable $now): array
    {
        $start = $now->startOfWeek();
        $counts = Reservation::query()->forOrganization($organization->id)
            ->whereBetween('starts_at', [$start->utc(), $start->addDays(7)->utc()])
            ->get(['starts_at'])
            ->groupBy(fn (Reservation $reservation) => $reservation->starts_at->setTimezone(Timezones::resolve($organization->timezone))->format('Y-m-d'))
            ->map->count();

        return collect(range(0, 6))->map(fn (int $day) => [
            'date' => $start->addDays($day)->format('Y-m-d'),
            'count' => $counts[$start->addDays($day)->format('Y-m-d')] ?? 0,
        ])->all();
    }

    private function mostBookedField(Collection $reservations): ?string
    {
        return $reservations->groupBy('football_field_id')->sortByDesc->count()->first()?->first()?->footballField?->name;
    }
}
