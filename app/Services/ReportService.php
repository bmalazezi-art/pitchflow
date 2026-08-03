<?php

namespace App\Services;

use App\Enums\OrganizationStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationCorrectionRequest;
use App\Models\WaitingListRequest;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(private readonly OperatingScheduleService $schedule) {}

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
        $monthReservations = (clone $month)->with('footballField:id,name')->get();
        $todayReservations = (clone $today)
            ->with('footballField:id,name')
            ->orderBy('starts_at')
            ->get([
                'id', 'organization_id', 'football_field_id', 'customer_name', 'starts_at', 'ends_at',
                'status', 'payment_status', 'price', 'paid_amount', 'currency',
            ]);

        $activeToday = $todayReservations->filter(fn (Reservation $reservation) => in_array($reservation->status, [
            ReservationStatus::Pending,
            ReservationStatus::Confirmed,
            ReservationStatus::Completed,
        ], true));

        $occupiedHours = $todayReservations
            ->filter(fn (Reservation $reservation) => in_array($reservation->status, [
                ReservationStatus::Pending,
                ReservationStatus::Confirmed,
                ReservationStatus::Completed,
                ReservationStatus::NoShow,
            ], true))
            ->sum(fn (Reservation $reservation) => $reservation->starts_at->diffInMinutes($reservation->ends_at) / 60);
        $availableHours = $this->capacityHours($organization, $now->startOfDay(), $now->startOfDay());

        return [
            'timezone' => $timezone,
            'currency' => $organization->currency,
            'today_date' => $now->toDateString(),
            'today_reservations' => $todayReservations->count(),
            'expected_revenue_today' => (float) $activeToday->sum('price'),
            'today_revenue' => (float) $todayReservations
                ->whereIn('payment_status', [PaymentStatus::Paid, PaymentStatus::Partial])
                ->sum('paid_amount'),
            'unpaid_reservations' => $activeToday
                ->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::Partial])
                ->count(),
            'cancellations_and_no_shows' => $todayReservations
                ->whereIn('status', [ReservationStatus::Cancelled, ReservationStatus::LateCancelled, ReservationStatus::NoShow])
                ->count(),
            'monthly_revenue' => (float) (clone $month)->whereIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::Partial->value])->sum('paid_amount'),
            'occupancy_rate' => round(min(100, ($occupiedHours / max(1, $availableHours)) * 100), 1),
            'today_timeline' => $todayReservations,
            'busiest_field_today' => $this->mostBookedField($todayReservations),
            'upcoming' => (clone $base)->with('footballField:id,name')->where('starts_at', '>=', now())
                ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
                ->orderBy('starts_at')->limit(6)->get([
                    'id', 'organization_id', 'football_field_id', 'customer_name', 'starts_at', 'ends_at',
                    'status', 'payment_status',
                ]),
            'weekly' => $this->weeklyCounts($organization, $now),
            'active_employees' => $organization->users()->where('role', 'employee')->count(),
            'peak_hours' => $monthReservations
                ->groupBy(fn (Reservation $reservation) => $reservation->starts_at->setTimezone($timezone)->format('H:00'))
                ->map->count()
                ->sortDesc()
                ->take(5),
            'most_booked_field' => $this->mostBookedField($monthReservations),
            'recent_activity' => ActivityLog::query()
                ->forOrganization($organization->id)
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(8)
                ->get(['id', 'organization_id', 'user_id', 'action', 'description', 'created_at']),
            'readiness' => $this->readiness($organization),
        ];
    }

    public function detailed(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $reservations = Reservation::query()
            ->forOrganization($organization->id)
            ->with(['footballField:id,name', 'cancelledByUser:id,name'])
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->get();

        $countableReservations = $reservations->whereNotIn('status', [
            ReservationStatus::Cancelled,
            ReservationStatus::LateCancelled,
            ReservationStatus::Voided,
        ]);
        $activeReservations = $reservations->whereIn('status', [
            ReservationStatus::Pending,
            ReservationStatus::Confirmed,
            ReservationStatus::Completed,
        ]);
        $totalHours = $countableReservations
            ->sum(fn (Reservation $reservation) => $reservation->starts_at->diffInMinutes($reservation->ends_at) / 60);
        $availableHours = $this->capacityHours($organization, $from, $to);
        $paymentStats = [
            'paid' => $this->paymentBucket($activeReservations, PaymentStatus::Paid),
            'partial' => $this->paymentBucket($activeReservations, PaymentStatus::Partial),
            'unpaid' => $this->paymentBucket($activeReservations, PaymentStatus::Unpaid),
        ];
        $cancelledReservations = $reservations->whereIn('status', [
            ReservationStatus::Cancelled,
            ReservationStatus::LateCancelled,
            ReservationStatus::NoShow,
            ReservationStatus::Voided,
        ]);
        $correctionRequests = ReservationCorrectionRequest::query()
            ->forOrganization($organization->id)
            ->whereBetween('created_at', [$from->utc(), $to->utc()]);
        $correctedReservations = ReservationCorrectionRequest::query()
            ->forOrganization($organization->id)
            ->where('status', 'resolved')
            ->whereBetween('reviewed_at', [$from->utc(), $to->utc()]);
        $waitingListRequests = WaitingListRequest::query()
            ->forOrganization($organization->id)
            ->whereBetween('created_at', [$from->utc(), $to->utc()]);
        $missingPriceCount = $activeReservations
            ->filter(fn (Reservation $reservation) => (float) $reservation->price <= 0)
            ->count();

        return [
            'reservation_count' => $countableReservations->count(),
            'collected_revenue' => (float) $activeReservations
                ->whereIn('payment_status', [PaymentStatus::Paid, PaymentStatus::Partial])
                ->sum('paid_amount'),
            'booked_revenue' => (float) $activeReservations->sum('price'),
            'occupancy_rate' => round(min(100, ($totalHours / $availableHours) * 100), 1),
            'walk_ins' => $countableReservations->where('is_walk_in', true)->count(),
            'no_shows' => $reservations->where('status', ReservationStatus::NoShow)->count(),
            'late_cancellations' => $reservations->where('status', ReservationStatus::LateCancelled)->count(),
            'total_cancellations' => $cancelledReservations->count(),
            'correction_requests' => (clone $correctionRequests)->count(),
            'corrected_reservations' => (clone $correctedReservations)->count(),
            'waiting_list_requests' => (clone $waitingListRequests)->count(),
            'notified_waiting_list_requests' => (clone $waitingListRequests)->where('status', 'notified')->count(),
            'paid_cancelled_revenue' => (float) $cancelledReservations
                ->whereIn('payment_status', [PaymentStatus::Paid, PaymentStatus::Partial])
                ->sum('paid_amount'),
            'cancellations_by_reason' => $cancelledReservations
                ->groupBy(fn (Reservation $reservation) => $reservation->cancellation_reason ?: 'unknown')
                ->map->count()
                ->sortDesc(),
            'cancellations_by_employee' => $cancelledReservations
                ->groupBy(fn (Reservation $reservation) => $reservation->cancelledByUser?->name ?: 'Unknown')
                ->map->count()
                ->sortDesc(),
            'paid_reservations' => $paymentStats['paid']['count'],
            'partial_reservations' => $paymentStats['partial']['count'],
            'unpaid_reservations' => $paymentStats['unpaid']['count'],
            'payment_stats' => $paymentStats,
            'unpaid_booking_count' => $activeReservations
                ->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::Partial])
                ->count(),
            'missing_price_reservation_count' => $missingPriceCount,
            'revenue_warning' => $reservations->isNotEmpty() && (float) $activeReservations->sum('price') <= 0
                ? 'Reservations found, but no price was saved for them.'
                : null,
            'most_booked_field' => $this->mostBookedField($countableReservations),
            'peak_hours' => $countableReservations->groupBy(fn (Reservation $reservation) => $reservation->starts_at->setTimezone(Timezones::resolve($organization->timezone))->format('H:00'))
                ->map->count()->sortDesc()->take(8),
        ];
    }

    private function paymentBucket(Collection $reservations, PaymentStatus $status): array
    {
        $bucket = $reservations->where('payment_status', $status);

        return [
            'count' => $bucket->count(),
            'paid_total' => (float) $bucket->sum('paid_amount'),
            'booked_total' => (float) $bucket->sum('price'),
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

    private function readiness(Organization $organization): array
    {
        $activeFields = $organization->footballFields()
            ->where('status', 'active')
            ->count();
        $employeeCount = $organization->users()
            ->where('role', 'employee')
            ->where('status', 'active')
            ->count();
        $hasContact = filled($organization->phone) && filled($organization->email);
        $hasLocation = filled($organization->city_id) && filled($organization->address);
        $isPublic = $organization->status === OrganizationStatus::Approved && $activeFields > 0;

        $items = [
            [
                'key' => 'businessProfile',
                'complete' => $hasContact && $hasLocation,
                'href' => '/settings/organization',
            ],
            [
                'key' => 'activeFields',
                'complete' => $activeFields > 0,
                'href' => '/fields',
            ],
            [
                'key' => 'employeesReady',
                'complete' => $employeeCount > 0,
                'href' => '/employees',
            ],
            [
                'key' => 'publicVisibilityReady',
                'complete' => $isPublic,
                'href' => '/settings/organization',
            ],
        ];

        return [
            'complete_count' => collect($items)->where('complete', true)->count(),
            'total_count' => count($items),
            'items' => $items,
            'warnings' => collect($items)
                ->reject(fn (array $item) => $item['complete'])
                ->pluck('key')
                ->values()
                ->all(),
        ];
    }

    private function capacityHours(
        Organization $organization,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): float {
        $fields = $organization->footballFields()
            ->where('status', 'active')
            ->with([
                'organization:id,timezone',
                'operatingHours',
                'operatingHourOverrides' => fn ($query) => $query->whereBetween('date', [$from->toDateString(), $to->toDateString()]),
            ])
            ->get();

        $capacity = 0;
        for ($date = $from->startOfDay(); $date->lessThanOrEqualTo($to->startOfDay()); $date = $date->addDay()) {
            foreach ($fields as $field) {
                $capacity += $this->schedule->hoursForDate($field, $date);
            }
        }

        return max(1, $capacity);
    }
}
