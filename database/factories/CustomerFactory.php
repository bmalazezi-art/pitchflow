<?php

namespace Database\Factories;

use App\Enums\ReliabilityStatus;
use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        $phone = '+3834'.fake()->unique()->numerify('#######');

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'phone' => $phone,
            'phone_normalized' => $phone,
            'reliability_status' => ReliabilityStatus::Reliable,
        ];
    }
}
