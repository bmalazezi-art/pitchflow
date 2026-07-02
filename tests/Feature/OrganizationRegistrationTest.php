<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Enums\UserRole;
use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_isolated_pending_organization_and_owner(): void
    {
        $city = City::factory()->create();

        $response = $this->post('/register', [
            'name' => 'Owner Name',
            'email' => 'owner@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'business_name' => 'Prishtina Arena',
            'owner_phone' => '+38344111222',
            'business_phone' => '+38344222333',
            'city_id' => $city->id,
            'business_address' => 'Main Street 1',
            'number_of_fields' => 2,
            'starting_price_per_hour' => 35,
            'opening_time' => '12:00',
            'closing_time' => '01:00',
            'amenities' => ['parking', 'lighting'],
            'preferred_language' => 'en',
        ]);

        $response->assertRedirect(route('approval.pending'));
        $this->assertDatabaseHas('organizations', [
            'name' => 'Prishtina Arena',
            'status' => OrganizationStatus::Pending->value,
            'timezone' => 'Europe/Pristina',
            'currency' => 'EUR',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'role' => UserRole::Owner->value]);
        $this->assertDatabaseCount('football_fields', 2);
        $this->assertDatabaseCount('operating_hours', 14);
        $this->assertDatabaseHas('football_fields', ['price_per_hour' => 35, 'opening_time' => '12:00']);
    }
}
