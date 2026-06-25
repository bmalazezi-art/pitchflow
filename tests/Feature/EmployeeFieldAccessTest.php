<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\User;
use App\Support\Timezones;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $start = now(Timezones::resolve($organization->timezone))->addDay()->startOfHour();
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
}
