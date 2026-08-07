<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FootballField;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeFieldAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_only_create_reservations_for_assigned_fields(): void
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $assigned = FootballField::factory()->for($organization)->create();
        $other = FootballField::factory()->for($organization)->create();
        $employee->assignedFields()->attach($assigned, ['organization_id' => $organization->id]);

        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $payload = [
            'customer_name' => 'Customer',
            'customer_phone' => '+38344123456',
            'football_field_id' => $other->id,
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->addHour()->format('Y-m-d\TH:i'),
            'payment_status' => 'unpaid',
        ];

        $this->actingAs($employee)->post('/reservations', $payload)->assertForbidden();
        $payload['football_field_id'] = $assigned->id;
        $this->actingAs($employee)->post('/reservations', $payload)->assertRedirect();
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_employee_booking_board_only_receives_assigned_fields_with_schedules(): void
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $assigned = FootballField::factory()->for($organization)->create(['name' => 'Main Pitch']);
        FootballField::factory()->for($organization)->create(['name' => 'Private Pitch']);
        OperatingHour::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $assigned->id,
            'day_of_week' => 1,
            'opening_time' => '15:00',
            'closing_time' => '23:00',
            'is_closed' => false,
        ]);
        $employee->assignedFields()->attach($assigned, ['organization_id' => $organization->id]);

        $this->actingAs($employee)->get('/calendar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reservations/Calendar')
            ->has('fields', 1)
            ->where('fields.0.id', $assigned->id)
            ->where('fields.0.name', 'Main Pitch')
            ->has('fields.0.operating_hours', 1)
            ->where('fields.0.operating_hours.0.opening_time', '15:00')
            ->where('timezone', 'Europe/Belgrade')
        );
    }

    public function test_employee_created_reservation_syncs_to_owner_calendar_and_assigned_employee_only(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $otherEmployee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $assigned = FootballField::factory()->for($organization)->create(['name' => 'Main Pitch']);
        $unassigned = FootballField::factory()->for($organization)->create(['name' => 'Training Pitch']);

        $employee->assignedFields()->attach($assigned, ['organization_id' => $organization->id]);
        $otherEmployee->assignedFields()->attach($unassigned, ['organization_id' => $organization->id]);

        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(18, 0);
        $payload = [
            'customer_name' => 'Calendar Sync Customer',
            'customer_phone' => '+38344111222',
            'football_field_id' => $assigned->id,
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->addHour()->format('Y-m-d\TH:i'),
            'payment_status' => 'unpaid',
        ];

        $this->actingAs($employee)->post('/reservations', $payload)->assertRedirect();

        $reservation = Reservation::query()->firstOrFail();
        $this->assertSame($organization->id, $reservation->organization_id);
        $this->assertSame($assigned->id, $reservation->football_field_id);
        $this->assertDatabaseHas('reservation_slots', [
            'organization_id' => $organization->id,
            'football_field_id' => $assigned->id,
            'reservation_id' => $reservation->id,
        ]);

        $calendarRange = [
            'from' => $start->toDateString(),
            'to' => $start->toDateString(),
        ];

        $this->actingAs($owner)->get('/calendar?'.http_build_query($calendarRange))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reservations/Calendar')
            ->has('reservations', 1)
            ->where('reservations.0.id', $reservation->id)
            ->where('reservations.0.organization_id', $organization->id)
            ->where('reservations.0.football_field_id', $assigned->id)
            ->has('fields', 2)
        );

        $this->actingAs($employee)->get('/calendar?'.http_build_query($calendarRange))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reservations/Calendar')
            ->has('reservations', 1)
            ->where('reservations.0.id', $reservation->id)
            ->has('fields', 1)
            ->where('fields.0.id', $assigned->id)
        );

        $this->actingAs($otherEmployee)->get('/calendar?'.http_build_query($calendarRange))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reservations/Calendar')
            ->has('reservations', 0)
            ->has('fields', 1)
            ->where('fields.0.id', $unassigned->id)
        );

        $this->actingAs($owner)->get('/reports?'.http_build_query($calendarRange))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('report.reservation_count', 1)
            ->where('report.booked_revenue', 40)
            ->where('report.unpaid_reservations', 1)
        );
    }

    public function test_midnight_reservation_is_returned_for_previous_business_date(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-01 10:00:00', 'Europe/Belgrade'));
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create(['name' => 'Arena']);
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);
        $start = CarbonImmutable::parse('2026-07-22 00:00:00', Timezones::resolve($organization->timezone));

        $this->actingAs($employee)->post('/reservations', [
            'customer_name' => 'Ahmed',
            'customer_phone' => '+38344123456',
            'football_field_id' => $field->id,
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->addHour()->format('Y-m-d\TH:i'),
            'payment_status' => 'unpaid',
        ])->assertRedirect();

        $reservation = Reservation::query()->firstOrFail();
        $range = ['from' => '2026-07-21', 'to' => '2026-07-21'];

        $this->actingAs($owner)->get('/calendar?'.http_build_query($range))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reservations/Calendar')
            ->where('initialDate', '2026-07-21')
            ->has('reservations', 1)
            ->where('reservations.0.id', $reservation->id)
            ->where('reservations.0.customer_name', 'Ahmed')
        );

        $this->actingAs($employee)->get('/calendar?'.http_build_query($range))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Reservations/Calendar')
            ->where('initialDate', '2026-07-21')
            ->has('reservations', 1)
            ->where('reservations.0.customer_name', 'Ahmed')
        );

        CarbonImmutable::setTestNow();
    }

    public function test_after_midnight_reservation_belongs_to_previous_business_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-06 12:00:00', Timezones::resolve('Europe/Pristina')));
        $organization = Organization::factory()->create(['timezone' => 'Europe/Pristina']);
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create([
            'name' => 'Overnight Arena',
            'opening_time' => '16:00',
            'closing_time' => '01:00',
        ]);
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);
        OperatingHour::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'day_of_week' => 4,
            'opening_time' => '16:00',
            'closing_time' => '01:00',
            'is_closed' => false,
        ]);
        OperatingHour::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'day_of_week' => 5,
            'opening_time' => '16:00',
            'closing_time' => '01:00',
            'is_closed' => true,
        ]);

        $this->actingAs($employee)->post('/reservations', [
            'customer_name' => 'Night Customer',
            'customer_phone' => '+38344123456',
            'football_field_id' => $field->id,
            'starts_at' => '2026-08-07T00:00',
            'ends_at' => '2026-08-07T01:00',
            'payment_status' => 'unpaid',
        ])->assertRedirect();

        $reservation = Reservation::query()->firstOrFail();

        $this->actingAs($owner)->get('/calendar?'.http_build_query(['from' => '2026-08-06', 'to' => '2026-08-06']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reservations', 1)
                ->where('reservations.0.id', $reservation->id));

        $this->actingAs($owner)->get('/calendar?'.http_build_query(['from' => '2026-08-07', 'to' => '2026-08-07']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('reservations', 0));

        CarbonImmutable::setTestNow();
    }
}
