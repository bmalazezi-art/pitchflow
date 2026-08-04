<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\WaitingListRequest;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicWaitingListController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'football_field_id' => ['required', 'integer', 'exists:football_fields,id'],
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9 ]*[0-9][0-9 ]*$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'phone.regex' => 'Numri i telefonit nuk është valid.',
        ]);

        $field = FootballField::query()
            ->with('organization:id,timezone,status')
            ->publicReady()
            ->whereHas('organization', fn ($query) => Organization::constrainEligibleForPublicDirectory($query))
            ->findOrFail($validated['football_field_id']);
        $timezone = Timezones::resolve($field->organization->timezone);
        $startsAt = CarbonImmutable::parse($validated['starts_at'], $timezone);
        $endsAt = CarbonImmutable::parse($validated['ends_at'], $timezone);
        $reservation = filled($validated['reservation_id'] ?? null)
            ? Reservation::query()
                ->whereKey($validated['reservation_id'])
                ->where('football_field_id', $field->id)
                ->first()
            : null;

        if (
            ! $reservation
            || ! in_array($reservation->status->value, ReservationStatus::blockedValues(), true)
            || ! $reservation->starts_at->equalTo($startsAt->utc())
            || ! $reservation->ends_at->equalTo($endsAt->utc())
        ) {
            throw ValidationException::withMessages([
                'reservation_id' => __('messages.waiting_list_reserved_slot_required'),
            ]);
        }

        WaitingListRequest::query()->create([
            'organization_id' => $field->organization_id,
            'football_field_id' => $field->id,
            'reservation_id' => $reservation->id,
            'date' => $startsAt->toDateString(),
            'start_time' => $startsAt->format('H:i:s'),
            'end_time' => $endsAt->format('H:i:s'),
            'customer_name' => $validated['customer_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'note' => $validated['note'] ?? null,
            'status' => 'waiting',
            'expires_at' => $endsAt->endOfDay()->utc(),
        ]);

        return back()->with('success', __('messages.waiting_list_joined'));
    }
}
