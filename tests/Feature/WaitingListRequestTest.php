<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WaitingListRequest;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WaitingListRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_public_visitor_can_join_waiting_list_without_customer_account(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'Europe/Pristina']);
        $field = FootballField::factory()->for($organization)->create();
        $customer = Customer::factory()->for($organization)->create();
        $start = CarbonImmutable::parse('2026-07-24 18:00', Timezones::resolve($organization->timezone));
        $reservation = Reservation::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'starts_at' => $start->utc(),
            'ends_at' => $start->addHour()->utc(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'price' => 20,
            'paid_amount' => 0,
            'currency' => 'EUR',
        ]);

        $this->post(route('waiting-list.store'), [
            'football_field_id' => $field->id,
            'reservation_id' => $reservation->id,
            'starts_at' => '2026-07-24T18:00',
            'ends_at' => '2026-07-24T19:00',
            'customer_name' => 'Waiting Visitor',
            'phone' => '+38344111222',
            'note' => 'Please call after 17:00.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('waiting_list_requests', [
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'reservation_id' => $reservation->id,
            'date' => '2026-07-24 00:00:00',
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'customer_name' => 'Waiting Visitor',
            'phone' => '+38344111222',
            'note' => 'Please call after 17:00.',
            'status' => 'waiting',
        ]);
    }

    public function test_public_waiting_list_request_must_match_a_reserved_slot(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'Europe/Pristina']);
        $field = FootballField::factory()->for($organization)->create();
        $start = CarbonImmutable::parse('2026-07-24 18:00', Timezones::resolve($organization->timezone));

        $this->post(route('waiting-list.store'), [
            'football_field_id' => $field->id,
            'starts_at' => '2026-07-24T18:00',
            'ends_at' => '2026-07-24T19:00',
            'customer_name' => 'Waiting Visitor',
            'phone' => '+38344111222',
        ])->assertSessionHasErrors('reservation_id');

        $this->assertDatabaseMissing('waiting_list_requests', [
            'football_field_id' => $field->id,
            'date' => $start->toDateString(),
            'phone' => '+38344111222',
        ]);
    }

    public function test_employee_booking_board_shows_waiting_list_requests_for_assigned_reserved_slots(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'Europe/Pristina']);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create();
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);
        $customer = Customer::factory()->for($organization)->create();
        $start = CarbonImmutable::now(Timezones::resolve($organization->timezone))->addDay()->setTime(18, 0);
        $reservation = Reservation::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'starts_at' => $start->utc(),
            'ends_at' => $start->addHour()->utc(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'price' => 20,
            'paid_amount' => 0,
            'currency' => 'EUR',
        ]);
        WaitingListRequest::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'reservation_id' => $reservation->id,
            'date' => $start->toDateString(),
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'customer_name' => 'Waiting Visitor',
            'phone' => '+38344111222',
            'note' => 'Can arrive fast.',
            'status' => 'waiting',
        ]);

        $this->actingAs($employee)
            ->get(route('calendar', ['from' => $start->toDateString(), 'to' => $start->toDateString()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reservations/Calendar')
                ->where('reservations.0.waiting_list_requests.0.customer_name', 'Waiting Visitor')
                ->where('reservations.0.waiting_list_requests.0.phone', '+38344111222')
                ->where('reservations.0.waiting_list_requests.0.note', 'Can arrive fast.'));
    }

    public function test_cancelled_reservation_returns_waiting_customers_for_manual_notification(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'Europe/Pristina']);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create();
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);
        $customer = Customer::factory()->for($organization)->create();
        $start = CarbonImmutable::now(Timezones::resolve($organization->timezone))->addDay()->setTime(18, 0);
        $reservation = Reservation::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'starts_at' => $start->utc(),
            'ends_at' => $start->addHour()->utc(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'price' => 20,
            'paid_amount' => 0,
            'currency' => 'EUR',
        ]);
        $waiting = WaitingListRequest::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'reservation_id' => $reservation->id,
            'date' => $start->toDateString(),
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'customer_name' => 'Waiting Visitor',
            'phone' => '+38344111222',
            'email' => 'visitor@example.com',
            'note' => 'Call me quickly.',
            'status' => 'waiting',
        ]);

        $this->actingAs($employee)
            ->delete(route('reservations.destroy', $reservation), ['reason' => 'customer_called'])
            ->assertRedirect()
            ->assertSessionHas('waiting_list_requests.count', 1)
            ->assertSessionHas('waiting_list_requests.requests.0.customer_name', 'Waiting Visitor')
            ->assertSessionHas('waiting_list_requests.requests.0.phone', '+38344111222')
            ->assertSessionHas('waiting_list_requests.requests.0.note', 'Call me quickly.');

        $this->actingAs($employee)
            ->patch(route('waiting-list.notified', $waiting))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('waiting_list_requests', [
            'id' => $waiting->id,
            'status' => 'notified',
        ]);
    }

    public function test_employee_cannot_mark_waiting_request_notified_for_unassigned_field(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'Europe/Pristina']);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $assignedField = FootballField::factory()->for($organization)->create();
        $otherField = FootballField::factory()->for($organization)->create();
        $employee->assignedFields()->attach($assignedField, ['organization_id' => $organization->id]);
        $waiting = WaitingListRequest::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $otherField->id,
            'date' => '2026-07-24',
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'customer_name' => 'Waiting Visitor',
            'phone' => '+38344111222',
            'status' => 'waiting',
        ]);

        $this->actingAs($employee)
            ->patch(route('waiting-list.notified', $waiting))
            ->assertForbidden();

        $this->assertSame('waiting', $waiting->refresh()->status);
    }

    public function test_public_availability_exposes_reserved_slot_id_without_customer_details(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-24 12:00', 'Europe/Belgrade'));
        $organization = Organization::factory()->create(['timezone' => 'Europe/Pristina']);
        $field = FootballField::factory()->for($organization)->create(['city_id' => $organization->city_id]);
        $customer = Customer::factory()->for($organization)->create();
        $start = CarbonImmutable::parse('2026-07-24 18:00', Timezones::resolve($organization->timezone));
        $reservation = Reservation::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'customer_id' => $customer->id,
            'customer_name' => 'Private Name',
            'customer_phone' => '+38344000000',
            'starts_at' => $start->utc(),
            'ends_at' => $start->addHour()->utc(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'price' => 20,
            'paid_amount' => 0,
            'currency' => 'EUR',
        ]);

        $this->get(route('availability', [
            'city' => $organization->city_id,
            'business' => $organization->id,
            'date' => '2026-07-24',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Availability')
            ->where('pitchAvailability.0.slots.6.status', 'reserved')
            ->where('pitchAvailability.0.slots.6.reservation_id', $reservation->id)
            ->missing('pitchAvailability.0.slots.6.customer_name')
            ->missing('pitchAvailability.0.slots.6.customer_phone'));
    }
}
