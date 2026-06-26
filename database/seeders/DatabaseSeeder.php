<?php

namespace Database\Seeders;

use App\Enums\UserRole;
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
        $this->call(CitySeeder::class);

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

        if (app()->environment(['local', 'testing'])) {
            $this->call(LocalTestingSeeder::class);
        }
    }
}
