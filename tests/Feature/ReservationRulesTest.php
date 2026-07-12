<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
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

    public function test_cancelling_inside_the_window_marks_a_late_cancellation_and_releases_slot(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addHour()->startOfHour();
        $field->update(['opening_time' => '00:00', 'closing_time' => '00:00']);

        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservationId = (int) \DB::table('reservations')->value('id');
        $this->actingAs($employee)->delete("/reservations/{$reservationId}", ['reason' => 'Customer cancelled'])
            ->assertRedirect();

        $this->assertDatabaseHas('reservations', ['id' => $reservationId, 'status' => ReservationStatus::LateCancelled->value]);
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

    public function test_completed_reservation_is_locked_for_payment_and_cancellation_actions(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $this->actingAs($employee)->post('/reservations', $this->payload($field, $start));
        $reservation = Reservation::query()->firstOrFail();

        $this->actingAs($employee)->patch("/reservations/{$reservation->id}/complete")->assertRedirect();
        $this->assertSame(ReservationStatus::Completed, $reservation->refresh()->status);

        $this->actingAs($employee)->patch("/reservations/{$reservation->id}/paid")
            ->assertSessionHasErrors('payment_status');
        $this->actingAs($employee)->delete("/reservations/{$reservation->id}", ['reason' => 'Customer called'])
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

        $this->actingAs($employee)->delete("/reservations/{$reservation->id}", ['reason' => 'Customer did not arrive'])
            ->assertRedirect();
        $this->assertSame(ReservationStatus::LateCancelled, $reservation->refresh()->status);
        Carbon::setTestNow();
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
