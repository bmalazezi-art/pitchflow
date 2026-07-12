<?php

namespace App\Services;

use App\Enums\FieldStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationConflictException;
use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function __construct(
        private readonly PhoneNormalizer $phones,
        private readonly ReliabilityService $reliability,
        private readonly ActivityLogger $activity,
        private readonly OperatingScheduleService $schedule,
    ) {}

    public function create(Organization $organization, array $data, int $actorId): Reservation
    {
        return DB::transaction(function () use ($organization, $data, $actorId) {
            $field = FootballField::query()
                ->forOrganization($organization->id)
                ->with(['organization:id,timezone', 'operatingHours'])
                ->lockForUpdate()
                ->findOrFail($data['football_field_id']);

            $this->ensureFieldBookable($field);
            [$startsAt, $endsAt] = $this->utcRange($organization, $data);
            $this->ensureValidRange($startsAt, $endsAt);
            $this->ensureWithinOperatingHours($field, $startsAt, $endsAt);

            $normalizedPhone = $this->phones->normalize($data['customer_phone']);
            $customer = Customer::withTrashed()->firstOrNew([
                'organization_id' => $organization->id,
                'phone_normalized' => $normalizedPhone,
            ]);
            $customer->fill([
                'name' => $data['customer_name'],
                'phone' => $data['customer_phone'],
            ]);
            $customer->deleted_at = null;
            $customer->save();

            $status = ReservationStatus::from($data['status'] ?? ReservationStatus::Confirmed->value);
            if (! in_array($status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
                throw new ReservationConflictException(__('messages.invalid_reservation_status'));
            }
            $reservation = Reservation::create([
                ...$data,
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $status,
                'price' => $data['price'] ?? (float) $field->price_per_hour * ($startsAt->diffInMinutes($endsAt) / 60),
                'currency' => $organization->currency,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            if ($status->blocksAvailability()) {
                $this->lockSlots($reservation);
            }

            $this->reliability->recalculate($customer);
            $this->activity->log('reservation_created', $reservation);

            return $reservation->load(['footballField', 'customer']);
        });
    }

    public function update(Reservation $reservation, Organization $organization, array $data, int $actorId): Reservation
    {
        return DB::transaction(function () use ($reservation, $organization, $data, $actorId) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $this->ensureEditable($reservation);
            $field = FootballField::query()
                ->forOrganization($organization->id)
                ->with(['organization:id,timezone', 'operatingHours'])
                ->lockForUpdate()
                ->findOrFail($data['football_field_id'] ?? $reservation->football_field_id);

            $this->ensureFieldBookable($field);
            $payload = [
                'starts_at' => $data['starts_at'] ?? $reservation->starts_at->setTimezone(Timezones::resolve($organization->timezone))->format('Y-m-d\TH:i'),
                'ends_at' => $data['ends_at'] ?? $reservation->ends_at->setTimezone(Timezones::resolve($organization->timezone))->format('Y-m-d\TH:i'),
            ];
            [$startsAt, $endsAt] = $this->utcRange($organization, $payload);
            $this->ensureValidRange($startsAt, $endsAt);
            $this->ensureWithinOperatingHours($field, $startsAt, $endsAt);

            $customer = $reservation->customer;
            if (isset($data['customer_phone']) && $this->phones->normalize($data['customer_phone']) !== $customer->phone_normalized) {
                $customer = Customer::query()->firstOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'phone_normalized' => $this->phones->normalize($data['customer_phone']),
                    ],
                    ['name' => $data['customer_name'], 'phone' => $data['customer_phone']],
                );
            } elseif (isset($data['customer_name'], $data['customer_phone'])) {
                $customer->update(['name' => $data['customer_name'], 'phone' => $data['customer_phone']]);
            }

            $reservation->slots()->delete();
            $nextStatus = ReservationStatus::from($data['status'] ?? $reservation->status->value);
            $this->ensureStatusTransition($reservation->status, $nextStatus);
            $reservation->fill([
                ...$data,
                'football_field_id' => $field->id,
                'customer_id' => $customer->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $nextStatus,
                'updated_by' => $actorId,
            ])->save();

            if ($reservation->status->blocksAvailability()) {
                $this->lockSlots($reservation);
            }

            $this->reliability->recalculate($customer);
            $this->activity->log('reservation_updated', $reservation);

            return $reservation->refresh()->load(['footballField', 'customer']);
        });
    }

    public function cancel(Reservation $reservation, Organization $organization, int $actorId, ?string $reason): Reservation
    {
        return DB::transaction(function () use ($reservation, $organization, $actorId, $reason) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $this->ensureCancellable($reservation, $reason);
            $cutoff = $reservation->starts_at->subMinutes($organization->cancellation_window_minutes);
            $status = now()->greaterThan($cutoff)
                ? ReservationStatus::LateCancelled
                : ReservationStatus::Cancelled;

            $reservation->slots()->delete();
            $reservation->forceFill([
                'status' => $status,
                'cancellation_reason' => $reason,
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
                'updated_by' => $actorId,
            ])->save();

            $this->reliability->recalculate($reservation->customer);
            $this->activity->log('reservation_cancelled', $reservation, properties: ['status' => $status->value]);

            return $reservation->refresh();
        });
    }

    public function markPaid(Reservation $reservation, int $actorId): Reservation
    {
        return DB::transaction(function () use ($reservation, $actorId) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($reservation->status === ReservationStatus::Completed) {
                throw new ReservationConflictException(__('messages.reservation_locked'));
            }
            $reservation->forceFill([
                'payment_status' => PaymentStatus::Paid,
                'paid_amount' => $reservation->price,
                'updated_by' => $actorId,
            ])->save();
            $this->activity->log('reservation_marked_paid', $reservation);

            return $reservation->refresh();
        });
    }

    public function complete(Reservation $reservation, int $actorId): Reservation
    {
        return DB::transaction(function () use ($reservation, $actorId) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $this->ensureStatusTransition($reservation->status, ReservationStatus::Completed);
            $reservation->slots()->delete();
            $reservation->forceFill([
                'status' => ReservationStatus::Completed,
                'updated_by' => $actorId,
            ])->save();
            $this->reliability->recalculate($reservation->customer);
            $this->activity->log('reservation_completed', $reservation);

            return $reservation->refresh();
        });
    }

    public function suggestAlternatives(
        Organization $organization,
        int $fieldId,
        string $requestedStart,
        int $limit = 3,
    ): array {
        $field = FootballField::query()
            ->forOrganization($organization->id)
            ->with(['organization:id,timezone', 'operatingHours'])
            ->findOrFail($fieldId);
        $candidate = CarbonImmutable::parse($requestedStart, Timezones::resolve($organization->timezone))
            ->addHour()
            ->startOfHour();
        $suggestions = [];

        for ($attempt = 0; $attempt < 48 && count($suggestions) < $limit; $attempt++) {
            $candidateEnd = $candidate->addHour();
            $occupied = ReservationSlot::query()
                ->where('football_field_id', $field->id)
                ->where('starts_at', $candidate->utc())
                ->exists();

            if (! $occupied && $this->schedule->contains($field, $candidate->utc(), $candidateEnd->utc())) {
                $suggestions[] = [
                    'starts_at' => $candidate->format('Y-m-d\TH:i'),
                    'ends_at' => $candidateEnd->format('Y-m-d\TH:i'),
                    'label' => $candidate->format('D, M j · H:i').'–'.$candidateEnd->format('H:i'),
                ];
            }

            $candidate = $candidate->addHour();
        }

        return $suggestions;
    }

    private function lockSlots(Reservation $reservation): void
    {
        $slot = CarbonImmutable::parse($reservation->starts_at)->startOfHour();
        $end = CarbonImmutable::parse($reservation->ends_at);

        try {
            while ($slot->lessThan($end)) {
                $reservation->slots()->create([
                    'organization_id' => $reservation->organization_id,
                    'football_field_id' => $reservation->football_field_id,
                    'starts_at' => $slot,
                ]);
                $slot = $slot->addHour();
            }
        } catch (QueryException $exception) {
            throw new ReservationConflictException(__('messages.reservation_conflict'), previous: $exception);
        }
    }

    private function utcRange(Organization $organization, array $data): array
    {
        return [
            CarbonImmutable::parse($data['starts_at'], Timezones::resolve($organization->timezone))->utc(),
            CarbonImmutable::parse($data['ends_at'], Timezones::resolve($organization->timezone))->utc(),
        ];
    }

    private function ensureValidRange(CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        if (
            $endsAt->lessThanOrEqualTo($startsAt)
            || $startsAt->minute !== 0
            || $endsAt->minute !== 0
            || $startsAt->diffInMinutes($endsAt) % 60 !== 0
        ) {
            throw new ReservationConflictException(__('messages.invalid_reservation_range'));
        }
    }

    private function ensureFieldBookable(FootballField $field): void
    {
        if ($field->status !== FieldStatus::Active) {
            throw new ReservationConflictException(__('messages.field_unavailable'));
        }
    }

    private function ensureEditable(Reservation $reservation): void
    {
        if ($reservation->status === ReservationStatus::Completed) {
            throw new ReservationConflictException(__('messages.reservation_locked'));
        }

        if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
            throw new ReservationConflictException(__('messages.invalid_reservation_status'));
        }

        if ($reservation->starts_at->lessThanOrEqualTo(now())) {
            throw new ReservationConflictException(__('messages.past_reservation_edit_forbidden'));
        }
    }

    private function ensureCancellable(Reservation $reservation, ?string $reason): void
    {
        if ($reservation->status === ReservationStatus::Completed) {
            throw new ReservationConflictException(__('messages.completed_reservation_cancel_forbidden'));
        }

        if ($reservation->starts_at->lessThanOrEqualTo(now()) && blank($reason)) {
            throw new ReservationConflictException(__('messages.past_reservation_cancel_reason_required'));
        }
    }

    private function ensureStatusTransition(ReservationStatus $current, ReservationStatus $next): void
    {
        if ($current === $next) {
            return;
        }

        $allowed = match ($current) {
            ReservationStatus::Pending => [ReservationStatus::Confirmed],
            ReservationStatus::Confirmed => [ReservationStatus::Completed, ReservationStatus::NoShow],
            default => [],
        };

        if (! in_array($next, $allowed, true)) {
            throw new ReservationConflictException(__('messages.invalid_reservation_status'));
        }
    }

    private function ensureWithinOperatingHours(
        FootballField $field,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): void {
        if (! $this->schedule->contains($field, $startsAt, $endsAt)) {
            throw new ReservationConflictException(__('messages.outside_operating_hours'));
        }
    }
}
