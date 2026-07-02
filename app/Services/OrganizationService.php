<?php

namespace App\Services;

use App\Enums\FieldStatus;
use App\Enums\OrganizationStatus;
use App\Enums\UserRole;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationService
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $organization = Organization::create([
                'city_id' => $data['city_id'],
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'email' => $data['email'],
                'phone' => $data['business_phone'],
                'address' => $data['business_address'],
                'amenities' => array_values($data['amenities'] ?? []),
                'number_of_fields' => $data['number_of_fields'],
                'status' => OrganizationStatus::Pending,
                'timezone' => 'Europe/Pristina',
                'currency' => 'EUR',
                'locale' => $data['preferred_language'] ?? 'en',
                'cancellation_window_minutes' => 120,
            ]);

            $user = User::create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['owner_phone'],
                'preferred_language' => $data['preferred_language'] ?? 'en',
                'password' => $data['password'],
                'role' => UserRole::Owner,
            ]);

            foreach (range(1, $data['number_of_fields']) as $pitchNumber) {
                $field = FootballField::create([
                    'organization_id' => $organization->id,
                    'city_id' => $organization->city_id,
                    'name' => $data['number_of_fields'] === 1 ? $data['business_name'] : "{$data['business_name']} - Pitch {$pitchNumber}",
                    'slug' => "pitch-{$pitchNumber}",
                    'address' => $organization->address,
                    'status' => FieldStatus::Active,
                    'price_per_hour' => $data['starting_price_per_hour'],
                    'opening_time' => $data['opening_time'],
                    'closing_time' => $data['closing_time'],
                ]);

                foreach (range(0, 6) as $day) {
                    $field->operatingHours()->create([
                        'organization_id' => $organization->id,
                        'day_of_week' => $day,
                        'opening_time' => $data['opening_time'],
                        'closing_time' => $data['closing_time'],
                        'is_closed' => false,
                    ]);
                }
            }

            $this->activity->log('organization_registered', $organization, organizationId: $organization->id);
            $this->subscriptions->syncForOrganization($organization);

            return $user;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $counter = 2;

        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
