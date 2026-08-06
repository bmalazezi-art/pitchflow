<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Timezones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_cannot_mutate_reservations_or_field_inventory(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create();
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $payload = $this->reservationPayload($field, $start);

        $this->actingAs($owner)->post('/reservations', $payload)->assertForbidden();

        $this->actingAs($employee)->post('/reservations', $payload)->assertRedirect();
        $reservation = Reservation::query()->firstOrFail();

        $this->actingAs($owner)->put("/reservations/{$reservation->id}", $payload)->assertForbidden();
        $this->actingAs($owner)->delete("/reservations/{$reservation->id}")->assertForbidden();
        $this->actingAs($owner)->patch("/reservations/{$reservation->id}/paid")->assertForbidden();
        $this->actingAs($owner)->patch("/reservations/{$reservation->id}/complete")->assertForbidden();
        $this->actingAs($owner)->post('/fields', [
            'name' => 'Extra Field',
            'status' => 'active',
            'price_per_hour' => 40,
            'opening_time' => '12:00',
            'closing_time' => '01:00',
        ])->assertForbidden();
        $this->actingAs($owner)->delete("/fields/{$field->id}")->assertForbidden();
    }

    public function test_owner_and_assigned_employee_can_add_customer_notes(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $otherEmployee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create();
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);
        $customer = Customer::factory()->for($organization)->create();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0)->utc();
        Reservation::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'price' => 40,
            'currency' => 'EUR',
        ]);

        $this->actingAs($owner)->post("/customers/{$customer->id}/notes", ['note' => 'Owner note'])->assertRedirect();
        $this->actingAs($otherEmployee)->post("/customers/{$customer->id}/notes", ['note' => 'Unassigned note'])->assertForbidden();
        $this->actingAs($employee)->post("/customers/{$customer->id}/notes", ['note' => 'Reliable customer'])->assertRedirect();

        $this->assertDatabaseHas('customer_notes', [
            'customer_id' => $customer->id,
            'user_id' => $employee->id,
            'note' => 'Reliable customer',
        ]);

        $note = CustomerNote::query()->where('user_id', $employee->id)->firstOrFail();
        $this->actingAs($owner)->put("/customers/{$customer->id}/notes/{$note->id}", ['note' => 'Owner edit'])->assertForbidden();
        $this->actingAs($otherEmployee)->put("/customers/{$customer->id}/notes/{$note->id}", ['note' => 'Other edit'])->assertForbidden();
        $this->actingAs($employee)->put("/customers/{$customer->id}/notes/{$note->id}", ['note' => 'Updated private note'])->assertRedirect();

        $this->assertDatabaseHas('customer_notes', [
            'id' => $note->id,
            'user_id' => $employee->id,
            'note' => 'Updated private note',
        ]);
    }

    public function test_employee_cannot_access_owner_business_controls(): void
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);

        $field = FootballField::factory()->for($organization)->create();
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);

        $this->actingAs($employee)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Dashboard')
                ->where('metrics.active_field_count', 1)
                ->has('metrics.upcoming'));
        $this->actingAs($employee)->get('/reports')->assertForbidden();
        $this->actingAs($employee)->get('/settings/organization')->assertForbidden();
        $this->actingAs($employee)->get('/employees')->assertForbidden();
    }

    public function test_employee_cannot_change_field_configuration(): void
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create();
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);

        $this->actingAs($employee)->put("/fields/{$field->id}", [
            'name' => 'Changed Field',
            'status' => 'maintenance',
            'price_per_hour' => 80,
            'opening_time' => '12:00',
            'closing_time' => '01:00',
        ])->assertForbidden();

        $this->assertNotSame('Changed Field', $field->refresh()->name);
    }

    public function test_employee_can_complete_and_mark_assigned_reservation_paid(): void
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create();
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);

        $this->actingAs($employee)->post('/reservations', $this->reservationPayload($field, $start))->assertRedirect();
        $reservation = Reservation::query()->firstOrFail();

        $this->actingAs($employee)->patch("/reservations/{$reservation->id}/paid")->assertRedirect();
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'payment_status' => 'paid',
            'paid_amount' => '40.00',
        ]);

        $this->actingAs($employee)->patch("/reservations/{$reservation->id}/complete")->assertRedirect();
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'completed']);
        $this->assertDatabaseCount('reservation_slots', 1);
    }

    public function test_owner_and_employee_can_open_and_update_their_own_profile(): void
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->get('/profile')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Profile')
                ->where('employee.id', $owner->id));
        $this->actingAs($owner)->put('/profile', [
            'name' => 'Owner User',
            'phone' => '+38344111112',
            'preferred_language' => 'en',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'name' => 'Owner User']);

        $this->actingAs($employee)->get('/profile')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Profile')
                ->where('employee.id', $employee->id));
        $this->actingAs($employee)->put('/profile', [
            'name' => 'Reception User',
            'phone' => '+38344111111',
            'preferred_language' => 'sq',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'name' => 'Reception User']);
    }

    private function reservationPayload(FootballField $field, $start): array
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
