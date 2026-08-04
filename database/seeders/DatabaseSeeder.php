<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
            $password = env('SUPER_ADMIN_PASSWORD');

            if (! is_string($password) || trim($password) === '') {
                $this->command?->warn('SUPER_ADMIN_PASSWORD is empty. Skipping Super Admin seed for security.');
            } else {
                User::query()->updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => env('SUPER_ADMIN_NAME', 'Platform Administrator'),
                        'password' => Hash::make($password),
                        'role' => UserRole::SuperAdmin,
                        'email_verified_at' => now(),
                    ],
                );
            }
        }

        if (app()->environment(['local', 'testing'])) {
            $this->call(LocalTestingSeeder::class);
        }
    }
}
