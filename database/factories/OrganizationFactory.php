<?php

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Models\City;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'city_id' => City::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'amenities' => [],
            'status' => OrganizationStatus::Approved,
            'number_of_fields' => 1,
            'timezone' => 'Europe/Pristina',
            'currency' => 'EUR',
            'locale' => 'en',
            'cancellation_window_minutes' => 120,
            'approved_at' => now(),
        ];
    }
}
