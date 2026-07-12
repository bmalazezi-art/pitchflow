<?php

namespace App\Http\Controllers;

use App\Exceptions\ReservationConflictException;
use App\Http\Requests\ReservationRequest;
use App\Models\FootballField;
use App\Models\Reservation;
use App\Services\ReservationService;
use App\Support\EmployeePermissions;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user()->isEmployee() && ! $request->user()->hasEmployeePermission(EmployeePermissions::VIEW_CALENDAR), 403);
        $user = $request->user();
        $organization = $user->organization;
        $timezone = Timezones::resolve($organization->timezone);
        $fieldIds = $this->accessibleFieldIds($request);
        $selectedReservation = $request->filled('reservation')
            ? Reservation::query()->forOrganization($organization->id)
                ->whereIn('football_field_id', $fieldIds)
                ->find($request->integer('reservation'))
            : null;
        $localNow = $selectedReservation
            ? CarbonImmutable::parse($selectedReservation->starts_at)->setTimezone($timezone)
            : CarbonImmutable::now($timezone);
        $from = CarbonImmutable::parse($request->input('from', $localNow->startOfMonth()->subWeek()), $timezone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($request->input('to', $localNow->endOfMonth()->addWeek()), $timezone)->endOfDay()->utc();

        $reservations = Reservation::query()
            ->forOrganization($organization->id)
            ->whereIn('football_field_id', $fieldIds)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->with(['footballField:id,name', 'customer:id,name,phone,reliability_status,total_reservations,no_shows,late_cancellations'])
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('Reservations/Calendar', [
            'reservations' => $reservations,
            'fields' => FootballField::query()
                ->forOrganization($organization->id)
                ->whereIn('id', $fieldIds)
                ->with(['operatingHours', 'operatingHourOverrides' => fn ($query) => $query
                    ->whereBetween('date', [$from->setTimezone($timezone)->toDateString(), $to->setTimezone($timezone)->toDateString()])])
                ->orderBy('name')
                ->get(['id', 'name', 'status', 'opening_time', 'closing_time']),
            'timezone' => $timezone,
            'selectedField' => $request->integer('field') ?: null,
            'selectedReservation' => $selectedReservation?->id,
        ]);
    }

    public function list(Request $request): Response
    {
        $query = trim((string) $request->input('search'));
        $dateFilter = (string) $request->input('date_filter', $request->input('filter', 'today'));
        $paymentFilter = (string) $request->input('payment_filter', 'all');
        $statusFilter = (string) $request->input('status_filter', 'all');
        $fieldIds = $this->accessibleFieldIds($request);
        $timezone = Timezones::resolve($request->user()->organization->timezone);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        [$from, $to] = $this->reservationListRange($request, $dateFilter, $today, $timezone);

        $baseQuery = Reservation::query()
            ->forOrganization($request->user()->organization_id)
            ->whereIn('football_field_id', $fieldIds)
            ->whereBetween('starts_at', [$from, $to])
            ->when($query, fn ($builder) => $builder->where(function ($nested) use ($query) {
                $nested->where('customer_name', 'like', "%{$query}%")
                    ->orWhere('customer_phone', 'like', "%{$query}%");
            }));

        $summaryReservations = (clone $baseQuery)->get(['id', 'status', 'payment_status']);
        $statusValue = fn (Reservation $reservation): string => is_string($reservation->status)
            ? $reservation->status
            : $reservation->status->value;
        $paymentValue = fn (Reservation $reservation): string => is_string($reservation->payment_status)
            ? $reservation->payment_status
            : $reservation->payment_status->value;

        $reservations = (clone $baseQuery)
            ->with('footballField:id,name')
            ->when(in_array($paymentFilter, ['paid', 'unpaid', 'partial'], true), fn ($builder) => $builder->where('payment_status', $paymentFilter))
            ->when(in_array($statusFilter, ['confirmed', 'pending', 'completed', 'no_show'], true), fn ($builder) => $builder->where('status', $statusFilter))
            ->when($statusFilter === 'cancelled', fn ($builder) => $builder->whereIn('status', ['cancelled', 'late_cancelled']))
            ->latest('starts_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'filters' => [
                'search' => $query,
                'date_filter' => $dateFilter,
                'payment_filter' => $paymentFilter,
                'status_filter' => $statusFilter,
                'from' => $request->input('from', $from->setTimezone($timezone)->toDateString()),
                'to' => $request->input('to', $to->setTimezone($timezone)->toDateString()),
            ],
            'summary' => [
                'total' => $summaryReservations->count(),
                'paid' => $summaryReservations->filter(fn (Reservation $reservation) => $paymentValue($reservation) === 'paid')->count(),
                'unpaid' => $summaryReservations->filter(fn (Reservation $reservation) => $paymentValue($reservation) === 'unpaid')->count(),
                'partial' => $summaryReservations->filter(fn (Reservation $reservation) => $paymentValue($reservation) === 'partial')->count(),
                'pending' => $summaryReservations->filter(fn (Reservation $reservation) => $statusValue($reservation) === 'pending')->count(),
                'cancelled' => $summaryReservations->filter(fn (Reservation $reservation) => in_array($statusValue($reservation), ['cancelled', 'late_cancelled'], true))->count(),
                'completed' => $summaryReservations->filter(fn (Reservation $reservation) => $statusValue($reservation) === 'completed')->count(),
            ],
            'timezone' => $timezone,
        ]);
    }

    public function store(ReservationRequest $request, ReservationService $service): RedirectResponse
    {
        $this->authorize('create', Reservation::class);
        $this->ensureFieldAccess($request, (int) $request->validated('football_field_id'));

        try {
            $service->create($request->user()->organization, $request->validated(), $request->user()->id);
        } catch (ReservationConflictException $exception) {
            return back()
                ->withErrors(['starts_at' => $exception->getMessage()])
                ->with('slot_suggestions', $service->suggestAlternatives(
                    $request->user()->organization,
                    (int) $request->validated('football_field_id'),
                    $request->validated('starts_at'),
                ));
        }

        return back()->with('success', __('messages.reservation_created'));
    }

    public function update(ReservationRequest $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->authorize('update', $reservation);
        $this->ensureFieldAccess($request, (int) $request->validated('football_field_id'));

        try {
            $service->update($reservation, $request->user()->organization, $request->validated(), $request->user()->id);
        } catch (ReservationConflictException $exception) {
            return back()
                ->withErrors(['starts_at' => $exception->getMessage()])
                ->with('slot_suggestions', $service->suggestAlternatives(
                    $request->user()->organization,
                    (int) $request->validated('football_field_id'),
                    $request->validated('starts_at'),
                ));
        }

        return back()->with('success', __('messages.reservation_updated'));
    }

    public function destroy(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->authorize('delete', $reservation);
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        try {
            $service->cancel($reservation, $request->user()->organization, $request->user()->id, $request->input('reason'));
        } catch (ReservationConflictException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
        }

        return back()->with('success', __('messages.reservation_cancelled'));
    }

    public function markPaid(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->authorize('update', $reservation);
        try {
            $service->markPaid($reservation, $request->user()->id);
        } catch (ReservationConflictException $exception) {
            return back()->withErrors(['payment_status' => $exception->getMessage()]);
        }

        return back()->with('success', __('messages.reservation_updated'));
    }

    public function complete(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->authorize('update', $reservation);
        try {
            $service->complete($reservation, $request->user()->id);
        } catch (ReservationConflictException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('success', __('messages.reservation_updated'));
    }

    private function accessibleFieldIds(Request $request): array
    {
        $user = $request->user();

        return $user->isOwner()
            ? FootballField::query()->forOrganization($user->organization_id)->pluck('id')->all()
            : $user->assignedFields()
                ->where('football_fields.organization_id', $user->organization_id)
                ->pluck('football_fields.id')
                ->all();
    }

    private function ensureFieldAccess(Request $request, int $fieldId): void
    {
        abort_unless(in_array($fieldId, $this->accessibleFieldIds($request), true), 403);
    }

    private function reservationListRange(Request $request, string $dateFilter, CarbonImmutable $today, string $timezone): array
    {
        return match ($dateFilter) {
            'tomorrow' => [$today->addDay()->utc(), $today->addDay()->endOfDay()->utc()],
            'week' => [$today->startOfWeek()->utc(), $today->endOfWeek()->utc()],
            'custom' => [
                CarbonImmutable::parse($request->input('from', $today->toDateString()), $timezone)->startOfDay()->utc(),
                CarbonImmutable::parse($request->input('to', $today->toDateString()), $timezone)->endOfDay()->utc(),
            ],
            default => [$today->utc(), $today->endOfDay()->utc()],
        };
    }
}
