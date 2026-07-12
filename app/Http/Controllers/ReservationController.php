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
        $filter = (string) $request->input('filter');
        $fieldIds = $this->accessibleFieldIds($request);
        $timezone = Timezones::resolve($request->user()->organization->timezone);
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $reservations = Reservation::query()
            ->forOrganization($request->user()->organization_id)
            ->whereIn('football_field_id', $fieldIds)
            ->with('footballField:id,name')
            ->when($query, fn ($builder) => $builder->where(function ($nested) use ($query) {
                $nested->where('customer_name', 'like', "%{$query}%")
                    ->orWhere('customer_phone', 'like', "%{$query}%");
            }))
            ->when($filter === 'today', fn ($builder) => $builder->whereBetween('starts_at', [$today->utc(), $today->endOfDay()->utc()]))
            ->when($filter === 'tomorrow', fn ($builder) => $builder->whereBetween('starts_at', [$today->addDay()->utc(), $today->addDay()->endOfDay()->utc()]))
            ->when($filter === 'week', fn ($builder) => $builder->whereBetween('starts_at', [$today->startOfWeek()->utc(), $today->endOfWeek()->utc()]))
            ->when(in_array($filter, ['paid', 'unpaid'], true), fn ($builder) => $builder->where('payment_status', $filter))
            ->when(in_array($filter, ['confirmed', 'pending', 'completed'], true), fn ($builder) => $builder->where('status', $filter))
            ->when($filter === 'cancelled', fn ($builder) => $builder->whereIn('status', ['cancelled', 'late_cancelled']))
            ->latest('starts_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'filters' => ['search' => $query, 'filter' => $filter],
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
        $service->cancel($reservation, $request->user()->organization, $request->user()->id, $request->input('reason'));

        return back()->with('success', __('messages.reservation_cancelled'));
    }

    public function markPaid(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->authorize('update', $reservation);
        $service->markPaid($reservation, $request->user()->id);

        return back()->with('success', __('messages.reservation_updated'));
    }

    public function complete(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->authorize('update', $reservation);
        $service->complete($reservation, $request->user()->id);

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
}
