<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Prishtinë', 'Prizren', 'Pejë', 'Mitrovicë', 'Ferizaj', 'Gjilan', 'Gjakovë'] as $name) {
            City::query()->firstOrCreate(
                ['name' => $name, 'country' => 'XK'],
                ['is_active' => true],
            );
        }
    }
}
