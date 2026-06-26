<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\City;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FootballFieldScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_configure_weekly_operating_hours(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $city = City::factory()->create();
        $hours = collect(range(0, 6))->map(fn (int $day) => [
            'day_of_week' => $day,
            'opening_time' => '14:00',
            'closing_time' => '23:00',
            'is_closed' => $day === 0,
        ])->all();

        $this->actingAs($owner)->post('/fields', [
            'name' => 'Weekly Field',
            'city_id' => $city->id,
            'address' => 'Sports Center',
            'status' => 'active',
            'price_per_hour' => 50,
            'opening_time' => '12:00',
            'closing_time' => '01:00',
            'operating_hours' => $hours,
        ])->assertRedirect();

        $this->assertDatabaseCount('operating_hours', 7);
        $this->assertDatabaseHas('operating_hours', [
            'day_of_week' => 0,
            'is_closed' => true,
            'opening_time' => '14:00',
            'closing_time' => '23:00',
        ]);
    }
}
