<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FootballField;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\User;
use App\Support\Timezones;
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
}
