<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['Prishtinë', 'Prizren', 'Pejë', 'Mitrovicë', 'Ferizaj', 'Gjilan', 'Gjakovë'] as $name) {
            City::query()->firstOrCreate(['name' => $name, 'country' => 'XK'], ['is_active' => true]);
        }

        if ($email = env('SUPER_ADMIN_EMAIL')) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => env('SUPER_ADMIN_NAME', 'Platform Administrator'),
                    'password' => env('SUPER_ADMIN_PASSWORD'),
                    'role' => UserRole::SuperAdmin,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
