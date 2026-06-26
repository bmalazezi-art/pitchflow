<?php

namespace App\Http\Controllers;

use App\Exceptions\ReservationConflictException;
use App\Http\Requests\ReservationRequest;
use App\Models\FootballField;
use App\Models\Reservation;
use App\Services\ReservationService;
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
        $user = $request->user();
        $organization = $user->organization;
        $timezone = Timezones::resolve($organization->timezone);
        $from = CarbonImmutable::parse($request->input('from', 'today'), $timezone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($request->input('to', $from->addDays(7)), $timezone)->endOfDay()->utc();
        $fieldIds = $this->accessibleFieldIds($request);

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
            'fields' => FootballField::query()->whereIn('id', $fieldIds)->orderBy('name')->get(),
            'timezone' => $organization->timezone,
        ]);
    }

    public function list(Request $request): Response
    {
        $query = trim((string) $request->input('search'));
        $fieldIds = $this->accessibleFieldIds($request);

        $reservations = Reservation::query()
            ->forOrganization($request->user()->organization_id)
            ->whereIn('football_field_id', $fieldIds)
            ->with('footballField:id,name')
            ->when($query, fn ($builder) => $builder->where(function ($nested) use ($query) {
                $nested->where('customer_name', 'like', "%{$query}%")
                    ->orWhere('customer_phone', 'like', "%{$query}%");
            }))
            ->latest('starts_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Reservations/Index', ['reservations' => $reservations, 'filters' => ['search' => $query]]);
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

    private function accessibleFieldIds(Request $request): array
    {
        $user = $request->user();

        return $user->isOwner()
            ? FootballField::query()->forOrganization($user->organization_id)->pluck('id')->all()
            : $user->assignedFields()->pluck('football_fields.id')->all();
    }

    private function ensureFieldAccess(Request $request, int $fieldId): void
    {
        abort_unless(in_array($fieldId, $this->accessibleFieldIds($request), true), 403);
    }
}
