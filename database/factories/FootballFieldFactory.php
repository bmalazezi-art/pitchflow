<?php

namespace Database\Factories;

use App\Enums\FieldStatus;
use App\Models\FootballField;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FootballField> */
class FootballFieldFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Field '.fake()->unique()->numberBetween(1, 9999);

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'status' => FieldStatus::Active,
            'price_per_hour' => 40,
            'opening_time' => '12:00',
            'closing_time' => '01:00',
        ];
    }
}
