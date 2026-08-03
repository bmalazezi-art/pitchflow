<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationCorrectionRequest;
use App\Models\User;
use App\Support\Timezones;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservations_outside_operating_hours_are_rejected(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(8, 0);

        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start))
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_cancelling_inside_the_window_marks_cancelled_and_releases_slot(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addHour()->startOfHour();
        $field->update(['opening_time' => '00:00', 'closing_time' => '00:00']);

        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservationId = (int) \DB::table('reservations')->value('id');
        $this->actingAs($employee)->delete("/reservations/{$reservationId}", ['reason' => 'customer_called'])
            ->assertRedirect();

        $this->assertDatabaseHas('reservations', ['id' => $reservationId, 'status' => ReservationStatus::Cancelled->value]);
        $this->assertDatabaseCount('reservation_slots', 0);
    }

    public function test_terminal_reservation_status_cannot_be_reopened(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservation = Reservation::query()->firstOrFail();

        $completed = [...$this->payload($field, $start), 'status' => 'completed'];
        $this->actingAs($employee)->put("/reservations/{$reservation->id}", $completed)->assertRedirect();
        $this->assertSame(ReservationStatus::Completed, $reservation->refresh()->status);

        $reopened = [...$this->payload($field, $start), 'status' => 'confirmed'];
        $this->actingAs($employee)->put("/reservations/{$reservation->id}", $reopened)
            ->assertSessionHasErrors('starts_at');
        $this->assertSame(ReservationStatus::Completed, $reservation->refresh()->status);
    }

    public function test_past_reservation_cannot_be_edited(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservation = Reservation::query()->firstOrFail();

        Carbon::setTestNow($reservation->starts_at->copy()->addMinute());

        $this->actingAs($employee)->put("/reservations/{$reservation->id}", [
            ...$this->payload($field, $start),
            'customer_name' => 'Changed Customer',
        ])->assertSessionHasErrors('starts_at');

        $this->assertSame('Customer', $reservation->refresh()->customer_name);
        Carbon::setTestNow();
    }

    public function test_reservation_phone_rejects_letters_on_create(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);

        $this->actingAs($employee)->post('/reservations', [
            ...$this->payload($field, $start),
            'customer_phone' => '044 ABC 123',
        ])->assertSessionHasErrors([
            'customer_phone' => 'Numri i telefonit nuk është valid.',
        ]);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_phone_accepts_numbers_spaces_and_leading_plus(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);

        $this->actingAs($employee)->post('/reservations', [
            ...$this->payload($field, $start),
            'customer_phone' => '+383 44 123 456',
        ])->assertRedirect();

        $this->assertDatabaseHas('reservations', [
            'customer_phone' => '+383 44 123 456',
        ]);
    }

    public function test_reservation_phone_rejects_letters_on_edit(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservation = Reservation::query()->firstOrFail();

        $this->actingAs($employee)->put("/reservations/{$reservation->id}", [
            ...$this->payload($field, $start),
            'customer_phone' => '+383 44 BAD 456',
        ])->assertSessionHasErrors([
            'customer_phone' => 'Numri i telefonit nuk është valid.',
        ]);

        $this->assertSame('+38344123456', $reservation->refresh()->customer_phone);
    }

    public function test_completed_reservation_is_locked_for_payment_and_cancellation_actions(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservation = Reservation::query()->firstOrFail();

        $this->actingAs($employee)->patch("/reservations/{$reservation->id}/complete")->assertRedirect();
        $this->assertSame(ReservationStatus::Completed, $reservation->refresh()->status);
        $this->assertDatabaseCount('reservation_slots', 1);

        $this->actingAs($employee)->patch("/reservations/{$reservation->id}/paid")
            ->assertSessionHasErrors('payment_status');
        $this->actingAs($employee)->delete("/reservations/{$reservation->id}", ['reason' => 'customer_called'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(ReservationStatus::Completed, $reservation->refresh()->status);
        $this->assertSame('unpaid', $reservation->payment_status->value);
    }

    public function test_past_reservation_cancellation_requires_a_reason(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservation = Reservation::query()->firstOrFail();

        Carbon::setTestNow($reservation->starts_at->copy()->addMinute());

        $this->actingAs($employee)->delete("/reservations/{$reservation->id}")
            ->assertSessionHasErrors('reason');
        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);

        $this->actingAs($employee)->delete("/reservations/{$reservation->id}", ['reason' => 'customer_called'])
            ->assertRedirect();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->refresh()->status);
        Carbon::setTestNow();
    }

    public function test_future_reservation_cancellation_requires_a_reason(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservation = Reservation::query()->firstOrFail();

        $this->actingAs($employee)->delete("/reservations/{$reservation->id}")
            ->assertSessionHasErrors('reason');
        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);

        $this->actingAs($employee)->delete("/reservations/{$reservation->id}", ['reason' => 'customer_called'])
            ->assertRedirect();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->refresh()->status);
    }

    public function test_employee_cancellation_saves_metadata_and_audit_log(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservation = Reservation::query()->firstOrFail();

        $this->actingAs($employee)->delete("/reservations/{$reservation->id}", [
            'reason' => 'customer_called',
            'note' => 'Ardit called reception.',
        ])->assertRedirect();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame('confirmed', $reservation->previous_status);
        $this->assertSame('customer_called', $reservation->cancellation_reason);
        $this->assertSame('Ardit called reception.', $reservation->cancellation_note);
        $this->assertSame($employee->id, $reservation->cancelled_by_user_id);
        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'action' => 'reservation_cancelled',
            'entity_id' => $reservation->id,
        ]);
    }

    public function test_completed_reservation_correction_can_reopen_immediately_when_employee_has_permission(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservation = Reservation::query()->firstOrFail();
        $this->actingAs($employee)->patch("/reservations/{$reservation->id}/complete")->assertRedirect();

        $this->actingAs($employee)->post("/reservations/{$reservation->id}/correction-requests", [
            'reason' => 'completed_by_mistake',
            'action' => 'reopen',
            'note' => 'Clicked completed by mistake.',
        ])->assertRedirect();

        $correction = ReservationCorrectionRequest::query()->firstOrFail();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
        $this->assertSame('resolved', $correction->refresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'action' => 'reservation_reopened',
            'entity_id' => $reservation->id,
        ]);
        $this->assertSame(1, ActivityLog::query()->where('action', 'correction_requested')->count());
    }

    public function test_owner_can_review_pending_completed_reservation_correction_request(): void
    {
        [$organization, $employee, $field] = $this->context();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservation = Reservation::query()->firstOrFail();
        $this->actingAs($employee)->patch("/reservations/{$reservation->id}/complete")->assertRedirect();

        $this->actingAs($employee)->post("/reservations/{$reservation->id}/correction-requests", [
            'reason' => 'payment_status_wrong',
            'note' => 'Customer said payment status is wrong.',
        ])->assertRedirect();

        $correction = ReservationCorrectionRequest::query()->firstOrFail();
        $this->assertSame('pending', $correction->status);

        $this->actingAs($owner)->patch("/reservation-correction-requests/{$correction->id}", [
            'action' => 'ignore',
            'reason' => 'Owner checked and no change is needed.',
        ])->assertRedirect();

        $this->assertSame(ReservationStatus::Completed, $reservation->refresh()->status);
        $this->assertSame('ignored', $correction->refresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'action' => 'correction_ignored',
            'entity_id' => $reservation->id,
        ]);
    }

    public function test_completed_reservation_correction_can_cancel_and_release_active_booking(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservation = Reservation::query()->firstOrFail();
        $this->actingAs($employee)->patch("/reservations/{$reservation->id}/complete")->assertRedirect();

        $this->actingAs($employee)->post("/reservations/{$reservation->id}/correction-requests", [
            'reason' => 'completed_by_mistake',
            'action' => 'cancel',
            'note' => 'Customer called and cancelled after it was marked completed by mistake.',
        ])->assertRedirect();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame('completed', $reservation->previous_status);
        $this->assertSame('correction_cancel', $reservation->cancellation_reason);
        $this->assertDatabaseCount('reservation_slots', 0);
        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'action' => 'reservation_cancelled',
            'entity_id' => $reservation->id,
        ]);
    }

    public function test_completed_reservation_correction_can_mark_no_show(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservation = Reservation::query()->firstOrFail();
        $this->actingAs($employee)->patch("/reservations/{$reservation->id}/complete")->assertRedirect();

        $this->actingAs($employee)->post("/reservations/{$reservation->id}/correction-requests", [
            'reason' => 'completed_by_mistake',
            'action' => 'no_show',
            'note' => 'Customer never arrived.',
        ])->assertRedirect();

        $this->assertSame(ReservationStatus::NoShow, $reservation->refresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'action' => 'marked_no_show',
            'entity_id' => $reservation->id,
        ]);
    }

    private function context(): array
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create();
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);

        return [$organization, $employee, $field];
    }

    private function payload(FootballField $field, $start): array
    {
        return [
            'customer_name' => 'Customer',
            'customer_phone' => '+38344123456',
            'football_field_id' => $field->id,
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->copy()->addHour()->format('Y-m-d\TH:i'),
            'payment_status' => 'unpaid',
        ];
    }
}
