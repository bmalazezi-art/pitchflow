<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\User;
use App\Support\Timezones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_backed_slots_prevent_double_booking(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $field = FootballField::factory()->for($organization)->create();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);
        $payload = [
            'customer_name' => 'First Customer',
            'customer_phone' => '+38344111111',
            'football_field_id' => $field->id,
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->addHour()->format('Y-m-d\TH:i'),
            'payment_status' => 'unpaid',
        ];

        $this->actingAs($owner)->post('/reservations', $payload)->assertRedirect();
        $payload['customer_name'] = 'Second Customer';
        $payload['customer_phone'] = '+38344222222';
        $this->actingAs($owner)->post('/reservations', $payload)
            ->assertSessionHasErrors('starts_at')
            ->assertSessionHas('slot_suggestions');

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('reservation_slots', 1);
    }
}
