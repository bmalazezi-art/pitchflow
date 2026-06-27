<?php

namespace App\Services;

use App\Enums\FieldStatus;
use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\FootballField;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Timezones;
use Carbon\CarbonImmutable;

class EmployeeDashboardService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    public function dashboard(User $employee): array
    {
        $organization = $employee->organization;
        $timezone = Timezones::resolve($organization->timezone);
        $now = CarbonImmutable::now($timezone);
        $todayStart = $now->startOfDay()->utc();
        $todayEnd = $now->endOfDay()->utc();
        $fields = FootballField::query()
            ->forOrganization($organization->id)
            ->whereIn('id', $employee->assignedFields()->select('football_fields.id'))
            ->with('organization:id,timezone')
            ->orderBy('name')
            ->get();
        $fieldIds = $fields->pluck('id');

        $todayReservations = Reservation::query()
            ->forOrganization($organization->id)
            ->whereIn('football_field_id', $fieldIds)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->with('footballField:id,name')
            ->orderBy('starts_at')
            ->get();
        $upcoming = Reservation::query()
            ->forOrganization($organization->id)
            ->whereIn('football_field_id', $fieldIds)
            ->where('starts_at', '>=', $now->utc())
            ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
            ->with('footballField:id,name')
            ->orderBy('starts_at')
            ->limit(5)
            ->get();
        $availableSlots = $fields
            ->sum(fn (FootballField $field) => collect($this->availability->slots($field, $now->toDateString()))
                ->where('status', 'available')->count());

        return [
            'timezone' => $timezone,
            'today_date' => $now->toDateString(),
            'today_reservations' => $todayReservations,
            'today_reservation_count' => $todayReservations->count(),
            'available_slots_today' => $availableSlots,
            'next_reservation' => $upcoming->first(),
            'active_field_count' => $fields->where('status', FieldStatus::Active)->count(),
            'assigned_fields' => $fields->map->only(['id', 'name', 'status']),
            'upcoming' => $upcoming,
            'recent_activity' => ActivityLog::query()
                ->forOrganization($organization->id)
                ->where('user_id', $employee->id)
                ->whereIn('action', [
                    'reservation_created', 'reservation_updated', 'reservation_cancelled',
                    'reservation_completed', 'reservation_marked_paid', 'customer_note_created',
                ])
                ->latest('created_at')
                ->limit(8)
                ->get(['id', 'action', 'created_at']),
        ];
    }
}
