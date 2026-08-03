<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Deçan',
            'Drenas',
            'Ferizaj',
            'Fushë Kosovë',
            'Gjakovë',
            'Gjilan',
            'Klinë',
            'Lipjan',
            'Malishevë',
            'Mitrovicë',
            'Obiliq',
            'Pejë',
            'Podujevë',
            'Prishtinë',
            'Prizren',
            'Shtime',
            'Suharekë',
            'Viti',
            'Vushtrri',
        ] as $name) {
            City::query()->firstOrCreate(
                ['name' => $name, 'country' => 'XK'],
                ['is_active' => true],
            );
        }
    }
}
