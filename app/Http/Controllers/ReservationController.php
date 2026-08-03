<?php

namespace App\Http\Controllers;

use App\Exceptions\ReservationConflictException;
use App\Http\Requests\ReservationRequest;
use App\Models\FootballField;
use App\Models\Reservation;
use App\Models\ReservationCorrectionRequest;
use App\Models\WaitingListRequest;
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
        $localFrom = CarbonImmutable::parse($request->input('from', $localNow->startOfMonth()->subWeek()), $timezone)->startOfDay();
        $localTo = CarbonImmutable::parse($request->input('to', $localNow->endOfMonth()->addWeek()), $timezone)->endOfDay();
        $from = $localFrom->utc();
        $to = $localTo->utc();

        $reservations = Reservation::query()
            ->forOrganization($organization->id)
            ->whereIn('football_field_id', $fieldIds)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->with([
                'footballField:id,name',
                'customer:id,name,phone,reliability_status,total_reservations,no_shows,late_cancellations',
                'waitingListRequests' => fn ($query) => $query
                    ->where('status', 'waiting')
                    ->orderBy('created_at')
                    ->select('id', 'organization_id', 'football_field_id', 'reservation_id', 'customer_name', 'phone', 'note', 'status', 'created_at'),
            ])
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
            'initialDate' => $selectedReservation
                ? CarbonImmutable::parse($selectedReservation->starts_at)->setTimezone($timezone)->toDateString()
                : ($request->filled('from') ? $localFrom->toDateString() : $localNow->toDateString()),
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
            'correctionRequests' => $this->correctionRequests($request),
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
            if ($exception->getMessage() === __('messages.missing_field_price')) {
                return back()->withErrors(['football_field_id' => $exception->getMessage()]);
            }
            if ($exception->getMessage() === __('messages.blocked_customer_reservation_forbidden')) {
                return back()->withErrors(['customer_phone' => $exception->getMessage()]);
            }

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
            if ($exception->getMessage() === __('messages.blocked_customer_reservation_forbidden')) {
                return back()->withErrors(['customer_phone' => $exception->getMessage()]);
            }

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
        $data = $request->validate([
            'reason' => ['required', 'string', 'in:customer_called,customer_no_show,weather_issue,field_unavailable,duplicate_wrong_booking,other'],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:reason,other'],
        ]);
        try {
            $cancelled = $service->cancel($reservation, $request->user()->organization, $request->user()->id, $data['reason'], $data['note'] ?? null);
        } catch (ReservationConflictException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
        }

        return back()
            ->with('success', __('messages.reservation_cancelled'))
            ->with('waiting_list_requests', $this->waitingListPayload($cancelled, $request));
    }

    public function requestCorrection(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->authorize('view', $reservation);
        $data = $request->validate([
            'reason' => ['required', 'string', 'in:completed_by_mistake,payment_status_wrong,wrong_customer_details,should_mark_no_show,other'],
            'action' => ['nullable', 'string', 'in:reopen,cancel,no_show', 'required_if:reason,completed_by_mistake'],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:reason,other', 'required_if:action,cancel'],
        ]);

        try {
            $correction = $service->requestCorrection($reservation, $request->user(), $data['reason'], $data['note'] ?? null, $data['action'] ?? null);
            if (($data['action'] ?? null) && $this->canApplyCorrectionAction($request, $reservation, $data['action'])) {
                $service->reviewCorrection($correction, $request->user(), $data['action'], $data['note'] ?? __('messages.correction_review_saved'));

                return back()->with('success', __('messages.correction_review_saved'));
            }
        } catch (ReservationConflictException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
        }

        return back()->with('success', __('messages.correction_request_sent'));
    }

    public function reviewCorrection(Request $request, ReservationCorrectionRequest $correctionRequest, ReservationService $service): RedirectResponse
    {
        abort_unless($request->user()->isOwner() || $request->user()->isSuperAdmin(), 403);
        abort_unless($request->user()->isSuperAdmin() || $request->user()->organization_id === $correctionRequest->organization_id, 403);

        $data = $request->validate([
            'action' => ['required', 'string', 'in:reopen,cancel,no_show,void,ignore'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $service->reviewCorrection($correctionRequest, $request->user(), $data['action'], $data['reason']);
        } catch (ReservationConflictException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
        }

        return back()->with('success', __('messages.correction_review_saved'));
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
            'yesterday' => [$today->subDay()->utc(), $today->subDay()->endOfDay()->utc()],
            'this_week', 'week' => [$today->startOfWeek()->utc(), $today->endOfWeek()->utc()],
            'last_week' => [$today->subWeek()->startOfWeek()->utc(), $today->subWeek()->endOfWeek()->utc()],
            'this_month' => [$today->startOfMonth()->utc(), $today->endOfMonth()->utc()],
            'custom' => [
                CarbonImmutable::parse($request->input('from', $today->toDateString()), $timezone)->startOfDay()->utc(),
                CarbonImmutable::parse($request->input('to', $today->toDateString()), $timezone)->endOfDay()->utc(),
            ],
            default => [$today->utc(), $today->endOfDay()->utc()],
        };
    }

    private function correctionRequests(Request $request): array
    {
        if (! $request->user()->isOwner() && ! $request->user()->isSuperAdmin()) {
            return [];
        }

        return ReservationCorrectionRequest::query()
            ->forOrganization($request->user()->organization_id)
            ->where('status', 'pending')
            ->with([
                'requester:id,name,role',
                'reservation:id,organization_id,football_field_id,customer_name,customer_phone,starts_at,ends_at,status,payment_status,price,currency',
                'reservation.footballField:id,name',
            ])
            ->latest()
            ->limit(20)
            ->get()
            ->toArray();
    }

    private function waitingListPayload(Reservation $reservation, Request $request): ?array
    {
        $timezone = Timezones::resolve($request->user()->organization->timezone);
        $startsAt = CarbonImmutable::parse($reservation->starts_at)->setTimezone($timezone);
        $endsAt = CarbonImmutable::parse($reservation->ends_at)->setTimezone($timezone);
        $waiting = WaitingListRequest::query()
            ->where('organization_id', $reservation->organization_id)
            ->where('football_field_id', $reservation->football_field_id)
            ->where(fn ($query) => $query
                ->where('reservation_id', $reservation->id)
                ->orWhere(fn ($slotQuery) => $slotQuery
                    ->where('date', $startsAt->toDateString())
                    ->where('start_time', $startsAt->format('H:i:s'))
                    ->where('end_time', $endsAt->format('H:i:s'))))
            ->where('status', 'waiting')
            ->orderBy('created_at')
            ->get(['id', 'customer_name', 'phone', 'email', 'note', 'created_at']);

        if ($waiting->isEmpty()) {
            return null;
        }

        $fieldName = $reservation->footballField?->name
            ?? FootballField::query()->whereKey($reservation->football_field_id)->value('name')
            ?? __('messages.field');

        return [
            'count' => $waiting->count(),
            'field_name' => $fieldName,
            'start_time' => $startsAt->format('H:i'),
            'end_time' => $endsAt->format('H:i'),
            'requests' => $waiting->map(fn (WaitingListRequest $item) => [
                'id' => $item->id,
                'customer_name' => $item->customer_name,
                'phone' => $item->phone,
                'email' => $item->email,
                'note' => $item->note,
                'created_at' => $item->created_at,
                'message' => __('messages.waiting_list_whatsapp_message', [
                    'name' => $item->customer_name,
                    'start_time' => $startsAt->format('H:i'),
                    'field_name' => $fieldName,
                ]),
            ])->values()->all(),
        ];
    }

    private function canApplyCorrectionAction(Request $request, Reservation $reservation, string $action): bool
    {
        $user = $request->user();

        if ($user->isOwner() || $user->isSuperAdmin()) {
            return true;
        }

        if ($action === 'reopen') {
            return $user->hasEmployeePermission(EmployeePermissions::EDIT_RESERVATIONS)
                && $user->assignedFields()->whereKey($reservation->football_field_id)->exists();
        }

        return in_array($action, ['cancel', 'no_show'], true)
            && $user->hasEmployeePermission(EmployeePermissions::CANCEL_RESERVATIONS)
            && $user->assignedFields()->whereKey($reservation->football_field_id)->exists();
    }
}
