<?php

namespace App\Services;

use App\Enums\FieldStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReliabilityStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationConflictException;
use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationCorrectionRequest;
use App\Models\ReservationSlot;
use App\Models\User;
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

            $customer = $this->customerFromPayload($organization, $data);
            $this->ensureCustomerBookable($customer);

            $status = ReservationStatus::from($data['status'] ?? ReservationStatus::Confirmed->value);
            if (! in_array($status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
                throw new ReservationConflictException(__('messages.invalid_reservation_status'));
            }
            $price = $this->reservationPrice($field, $startsAt, $endsAt, $data['price'] ?? null);
            $paymentStatus = PaymentStatus::from($data['payment_status'] ?? PaymentStatus::Unpaid->value);
            $paidAmount = (float) ($data['paid_amount'] ?? 0);
            if ($paymentStatus === PaymentStatus::Paid && $paidAmount <= 0) {
                $paidAmount = $price;
            }
            $reservation = Reservation::create([
                ...$data,
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'price' => $price,
                'paid_amount' => $paidAmount,
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

            $previousCustomer = $reservation->customer;
            $customer = isset($data['customer_name'], $data['customer_phone'])
                ? $this->customerFromPayload($organization, $data)
                : $previousCustomer;
            if ($previousCustomer->isNot($customer)) {
                $this->ensureCustomerBookable($customer);
            }

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

            $reservation->slots()->delete();
            if ($reservation->status->blocksAvailability()) {
                $this->lockSlots($reservation);
            }

            if ($previousCustomer->isNot($customer)) {
                $this->reliability->recalculate($previousCustomer);
            }
            $this->reliability->recalculate($customer);
            $this->activity->log('reservation_updated', $reservation);

            return $reservation->refresh()->load(['footballField', 'customer']);
        });
    }

    public function cancel(Reservation $reservation, Organization $organization, int $actorId, ?string $reason, ?string $note = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $actorId, $reason, $note) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $this->ensureCancellable($reservation, $reason, $note);
            $previousStatus = $reservation->status;
            $status = ReservationStatus::Cancelled;

            $reservation->slots()->delete();
            $reservation->forceFill([
                'status' => $status,
                'cancellation_reason' => $reason,
                'previous_status' => $previousStatus->value,
                'cancellation_note' => $note,
                'cancelled_by' => $actorId,
                'cancelled_by_user_id' => $actorId,
                'cancelled_at' => now(),
                'updated_by' => $actorId,
            ])->save();

            $this->reliability->recalculate($reservation->customer);
            $this->activity->log('reservation_cancelled', $reservation, properties: [
                'actor_role' => request()->user()?->role?->value,
                'old_status' => $previousStatus->value,
                'new_status' => $status->value,
                'reason' => $reason,
                'note' => $note,
            ]);

            return $reservation->refresh();
        });
    }

    public function requestCorrection(Reservation $reservation, User $actor, string $reason, ?string $note = null, ?string $requestedAction = null): ReservationCorrectionRequest
    {
        return DB::transaction(function () use ($reservation, $actor, $reason, $note, $requestedAction) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($reservation->status !== ReservationStatus::Completed) {
                throw new ReservationConflictException(__('messages.correction_only_completed'));
            }

            if ($requestedAction && ! in_array($requestedAction, ['reopen', 'cancel', 'no_show'], true)) {
                throw new ReservationConflictException(__('messages.invalid_correction_action'));
            }

            if ($requestedAction === 'cancel' && blank($note)) {
                throw new ReservationConflictException(__('messages.correction_cancel_note_required'));
            }

            $request = ReservationCorrectionRequest::query()->create([
                'organization_id' => $reservation->organization_id,
                'reservation_id' => $reservation->id,
                'requested_by_user_id' => $actor->id,
                'reason' => $reason,
                'requested_action' => $requestedAction,
                'note' => $note,
                'status' => 'pending',
            ]);

            $this->activity->log('correction_requested', $reservation, properties: [
                'actor_role' => $actor->role->value,
                'old_status' => $reservation->status->value,
                'new_status' => $reservation->status->value,
                'reason' => $reason,
                'requested_action' => $requestedAction,
                'note' => $note,
            ]);

            return $request;
        });
    }

    public function reviewCorrection(ReservationCorrectionRequest $request, User $actor, string $action, string $reason): Reservation
    {
        return DB::transaction(function () use ($request, $actor, $action, $reason) {
            $request = ReservationCorrectionRequest::query()
                ->with('reservation.customer')
                ->lockForUpdate()
                ->findOrFail($request->id);

            if ($request->status !== 'pending') {
                throw new ReservationConflictException(__('messages.correction_already_reviewed'));
            }

            $reservation = Reservation::query()->lockForUpdate()->findOrFail($request->reservation_id);
            $oldStatus = $reservation->status;
            $newStatus = match ($action) {
                'reopen' => ReservationStatus::Confirmed,
                'cancel' => ReservationStatus::Cancelled,
                'no_show' => ReservationStatus::NoShow,
                'void' => ReservationStatus::Voided,
                'ignore' => $reservation->status,
                default => throw new ReservationConflictException(__('messages.invalid_correction_action')),
            };

            if ($action !== 'ignore') {
                $reservation->slots()->delete();
                $reservation->forceFill([
                    'status' => $newStatus,
                    'previous_status' => $oldStatus->value,
                    'cancellation_reason' => in_array($action, ['cancel', 'no_show', 'void'], true) ? "correction_{$action}" : $reservation->cancellation_reason,
                    'cancellation_note' => in_array($action, ['cancel', 'no_show', 'void'], true) ? $reason : $reservation->cancellation_note,
                    'cancelled_by' => in_array($action, ['cancel', 'no_show', 'void'], true) ? $actor->id : $reservation->cancelled_by,
                    'cancelled_by_user_id' => in_array($action, ['cancel', 'no_show', 'void'], true) ? $actor->id : $reservation->cancelled_by_user_id,
                    'cancelled_at' => in_array($action, ['cancel', 'no_show', 'void'], true) ? now() : $reservation->cancelled_at,
                    'updated_by' => $actor->id,
                ])->save();

                if ($newStatus->blocksAvailability()) {
                    $this->lockSlots($reservation);
                }

                $this->reliability->recalculate($reservation->customer);
            }

            $request->forceFill([
                'status' => $action === 'ignore' ? 'ignored' : 'resolved',
                'review_action' => $action,
                'review_reason' => $reason,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            $this->activity->log(match ($action) {
                'reopen' => 'reservation_reopened',
                'void' => 'reservation_voided',
                'no_show' => 'marked_no_show',
                'cancel' => 'reservation_cancelled',
                default => 'correction_ignored',
            }, $reservation, properties: [
                'actor_role' => $actor->role->value,
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'reason' => $reason,
                'note' => $request->note,
                'correction_request_id' => $request->id,
            ]);

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
            $this->lockSlots($reservation);
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

    private function customerFromPayload(Organization $organization, array $data): Customer
    {
        $normalizedPhone = $this->phones->normalize($data['customer_phone']);
        $customer = Customer::withTrashed()
            ->where('organization_id', $organization->id)
            ->where(function ($query) use ($data, $normalizedPhone) {
                $query->where('phone_normalized', $normalizedPhone)
                    ->orWhere(fn ($fallback) => $fallback
                        ->where('name', $data['customer_name'])
                        ->where('phone', $data['customer_phone']));
            })
            ->first() ?? new Customer([
                'organization_id' => $organization->id,
                'phone_normalized' => $normalizedPhone,
            ]);
        $customer->fill([
            'name' => $data['customer_name'],
            'phone' => $data['customer_phone'],
            'phone_normalized' => $normalizedPhone,
        ]);
        $customer->deleted_at = null;
        $customer->save();

        return $customer;
    }

    private function ensureCustomerBookable(Customer $customer): void
    {
        if ($customer->reliability_status === ReliabilityStatus::HighRisk) {
            throw new ReservationConflictException(__('messages.blocked_customer_reservation_forbidden'));
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

    private function ensureCancellable(Reservation $reservation, ?string $reason, ?string $note): void
    {
        if ($reservation->status === ReservationStatus::Completed) {
            throw new ReservationConflictException(__('messages.completed_reservation_cancel_forbidden'));
        }

        if (in_array($reservation->status, [ReservationStatus::Cancelled, ReservationStatus::LateCancelled, ReservationStatus::NoShow, ReservationStatus::Voided], true)) {
            throw new ReservationConflictException(__('messages.invalid_reservation_status'));
        }

        if (blank($reason)) {
            throw new ReservationConflictException(__('messages.past_reservation_cancel_reason_required'));
        }

        if ($reason === 'other' && blank($note)) {
            throw new ReservationConflictException(__('messages.cancellation_other_note_required'));
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

    private function reservationPrice(FootballField $field, CarbonImmutable $startsAt, CarbonImmutable $endsAt, mixed $explicitPrice = null): float
    {
        if ($explicitPrice !== null && (float) $explicitPrice >= 0) {
            return round((float) $explicitPrice, 2);
        }

        $hourlyPrice = (float) $field->price_per_hour;
        if ($hourlyPrice <= 0) {
            throw new ReservationConflictException(__('messages.missing_field_price'));
        }

        return round($hourlyPrice * ($startsAt->diffInMinutes($endsAt) / 60), 2);
    }
}
