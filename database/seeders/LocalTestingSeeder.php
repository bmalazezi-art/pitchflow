<?php

namespace Database\Seeders;

use App\Enums\FieldStatus;
use App\Enums\OrganizationStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReliabilityStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalTestingSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        DB::transaction(function () {
            $city = City::query()->where('name', 'Prishtinë')->firstOrFail();

            $organization = Organization::query()->updateOrCreate(
                ['slug' => 'demo-football-center'],
                [
                    'city_id' => $city->id,
                    'name' => 'Demo Football Center',
                    'email' => 'organization@example.test',
                    'phone' => '+38344100000',
                    'address' => 'Demo Street 1',
                    'status' => OrganizationStatus::Approved,
                    'subscription_plan' => '3–5 Fields',
                    'number_of_fields' => 3,
                    'timezone' => 'Europe/Pristina',
                    'currency' => 'EUR',
                    'locale' => 'en',
                    'cancellation_window_minutes' => 120,
                    'approved_at' => now(),
                    'rejected_at' => null,
                    'suspended_at' => null,
                ],
            );

            $organization->subscriptions()->updateOrCreate(
                ['plan_name' => '3–5 Fields'],
                [
                    'price' => 0,
                    'billing_cycle' => 'monthly',
                    'status' => 'trial',
                    'started_at' => now(),
                    'expires_at' => now()->addMonth(),
                ],
            );

            $superAdmin = $this->user('super.admin@example.test', 'Local Super Admin', UserRole::SuperAdmin);
            $owner = $this->user('owner@example.test', 'Local Owner', UserRole::Owner, $organization->id, '+38344100001');
            $employee = $this->user('employee@example.test', 'Local Employee', UserRole::Employee, $organization->id, '+38344100002');

            $fields = collect([
                ['name' => 'Main Pitch', 'slug' => 'main-pitch', 'price' => 45],
                ['name' => 'North Pitch', 'slug' => 'north-pitch', 'price' => 40],
                ['name' => 'Training Pitch', 'slug' => 'training-pitch', 'price' => 30],
            ])->map(fn (array $field) => FootballField::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'slug' => $field['slug']],
                [
                    'city_id' => $city->id,
                    'name' => $field['name'],
                    'address' => 'Demo Street 1',
                    'status' => FieldStatus::Active,
                    'price_per_hour' => $field['price'],
                    'opening_time' => '12:00',
                    'closing_time' => '01:00',
                ],
            ));

            $this->seedDemoVenues();

            $primaryField = $fields->first();
            $employee->assignedFields()->syncWithoutDetaching([
                $primaryField->id => ['organization_id' => $organization->id],
            ]);

            $localNow = CarbonImmutable::now(Timezones::resolve($organization->timezone));
            $samples = [
                ['field' => 0, 'name' => 'Arben Demo', 'phone' => '+38344111001', 'start' => $localNow->subDay()->setTime(18, 0), 'status' => ReservationStatus::Completed, 'payment' => PaymentStatus::Paid],
                ['field' => 1, 'name' => 'Besart Demo', 'phone' => '+38344111002', 'start' => $localNow->subDay()->setTime(20, 0), 'status' => ReservationStatus::NoShow, 'payment' => PaymentStatus::Unpaid],
                ['field' => 0, 'name' => 'Drita Demo', 'phone' => '+38344111003', 'start' => $localNow->setTime(18, 0), 'status' => ReservationStatus::Confirmed, 'payment' => PaymentStatus::Partial],
                ['field' => 1, 'name' => 'Elira Demo', 'phone' => '+38344111004', 'start' => $localNow->setTime(19, 0), 'status' => ReservationStatus::Pending, 'payment' => PaymentStatus::Unpaid],
                ['field' => 2, 'name' => 'Faton Demo', 'phone' => '+38344111005', 'start' => $localNow->addDay()->setTime(21, 0), 'status' => ReservationStatus::Confirmed, 'payment' => PaymentStatus::Paid],
            ];

            foreach ($samples as $sample) {
                $field = $fields[$sample['field']];
                $startsAt = $sample['start']->utc();
                $endsAt = $sample['start']->addHour()->utc();
                $customer = $this->customer($organization->id, $field->id, $sample['name'], $sample['phone']);

                $reservation = Reservation::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'football_field_id' => $field->id,
                        'starts_at' => $startsAt,
                    ],
                    [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'customer_phone' => $customer->phone,
                        'ends_at' => $endsAt,
                        'status' => $sample['status'],
                        'payment_status' => $sample['payment'],
                        'price' => $field->price_per_hour,
                        'paid_amount' => match ($sample['payment']) {
                            PaymentStatus::Paid => $field->price_per_hour,
                            PaymentStatus::Partial => 20,
                            PaymentStatus::Unpaid => 0,
                        },
                        'currency' => $organization->currency,
                        'is_walk_in' => false,
                        'notes' => 'Local demo reservation.',
                        'created_by' => $owner->id,
                        'updated_by' => $owner->id,
                    ],
                );

                if ($sample['status']->blocksAvailability()) {
                    ReservationSlot::query()->updateOrCreate(
                        ['football_field_id' => $field->id, 'starts_at' => $startsAt],
                        [
                            'organization_id' => $organization->id,
                            'reservation_id' => $reservation->id,
                        ],
                    );
                }
            }

            $this->printCredentials($superAdmin, $owner, $employee);
        });
    }

    private function seedDemoVenues(): void
    {
        $venues = [
            ['city' => 'Prizren', 'name' => 'Getoari Sport Center', 'slug' => 'getoari-sport-center', 'phone' => '+383 44 210 111', 'pitches' => 2, 'address' => 'Rruga Tirana, Prizren', 'price' => 35],
            ['city' => 'Prizren', 'name' => 'Arena Sport', 'slug' => 'arena-sport', 'phone' => '+383 44 320 222', 'pitches' => 2, 'address' => 'Rruga William Walker, Prizren', 'price' => 40],
            ['city' => 'Prizren', 'name' => 'Andrra Sport Center', 'slug' => 'andrra-sport-center', 'phone' => '+383 45 410 333', 'pitches' => 1, 'address' => 'Rruga Janina, Prizren', 'price' => 32],
            ['city' => 'Prishtinë', 'name' => 'Rilindja Football Center', 'slug' => 'rilindja-football-center', 'phone' => '+383 44 510 444', 'pitches' => 3, 'address' => 'Rruga B, Prishtinë', 'price' => 45],
            ['city' => 'Prishtinë', 'name' => 'Princi Football Arena', 'slug' => 'princi-football-arena', 'phone' => '+383 44 620 555', 'pitches' => 2, 'address' => 'Veternik, Prishtinë', 'price' => 42],
            ['city' => 'Prishtinë', 'name' => 'Green Sport Arena', 'slug' => 'green-sport-arena', 'phone' => '+383 45 730 666', 'pitches' => 2, 'address' => 'Mati 1, Prishtinë', 'price' => 38],
            ['city' => 'Prishtinë', 'name' => 'Arena 7', 'slug' => 'arena-7', 'phone' => '+383 49 840 777', 'pitches' => 1, 'address' => 'Bregu i Diellit, Prishtinë', 'price' => 35],
        ];

        foreach ($venues as $venue) {
            $city = City::query()->where('name', $venue['city'])->firstOrFail();
            $organization = Organization::query()->updateOrCreate(
                ['slug' => $venue['slug']],
                [
                    'city_id' => $city->id,
                    'name' => $venue['name'],
                    'email' => $venue['slug'].'@example.test',
                    'phone' => $venue['phone'],
                    'address' => $venue['address'],
                    'status' => OrganizationStatus::Approved,
                    'subscription_plan' => $venue['pitches'] > 2 ? '3–5 Fields' : '1–2 Fields',
                    'number_of_fields' => $venue['pitches'],
                    'timezone' => 'Europe/Pristina',
                    'currency' => 'EUR',
                    'locale' => 'en',
                    'cancellation_window_minutes' => 120,
                    'approved_at' => now(),
                    'rejected_at' => null,
                    'suspended_at' => null,
                ],
            );

            $field = FootballField::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'slug' => $venue['slug'].'-main'],
                [
                    'city_id' => $city->id,
                    'name' => $venue['name'],
                    'address' => $venue['address'],
                    'status' => FieldStatus::Active,
                    'price_per_hour' => $venue['price'],
                    'opening_time' => '12:00',
                    'closing_time' => '01:00',
                ],
            );

            if (in_array($venue['name'], ['Arena Sport', 'Rilindja Football Center'], true)) {
                $hour = $venue['name'] === 'Arena Sport' ? 17 : 19;
                $this->seedDemoVenueReservation($organization, $field, $hour);
            }
        }
    }

    private function seedDemoVenueReservation(Organization $organization, FootballField $field, int $hour): void
    {
        $localStart = CarbonImmutable::now(Timezones::resolve($organization->timezone))->setTime($hour, 0);
        $startsAt = $localStart->utc();
        $endsAt = $localStart->addHour()->utc();
        $customer = $this->customer($organization->id, $field->id, $field->name.' Customer', '+38344129999');

        $reservation = Reservation::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'football_field_id' => $field->id,
                'starts_at' => $startsAt,
            ],
            [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'ends_at' => $endsAt,
                'status' => ReservationStatus::Confirmed,
                'payment_status' => PaymentStatus::Paid,
                'price' => $field->price_per_hour,
                'paid_amount' => $field->price_per_hour,
                'currency' => $organization->currency,
                'is_walk_in' => false,
                'notes' => 'Local demo reservation.',
            ],
        );

        ReservationSlot::query()->updateOrCreate(
            ['football_field_id' => $field->id, 'starts_at' => $startsAt],
            [
                'organization_id' => $organization->id,
                'reservation_id' => $reservation->id,
            ],
        );
    }

    private function user(string $email, string $name, UserRole $role, ?int $organizationId = null, ?string $phone = null): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'organization_id' => $organizationId,
                'name' => $name,
                'password' => self::PASSWORD,
                'role' => $role,
                'phone' => $phone,
                'preferred_language' => 'en',
                'email_verified_at' => now(),
            ],
        );
    }

    private function customer(int $organizationId, int $fieldId, string $name, string $phone): Customer
    {
        return Customer::query()->updateOrCreate(
            ['organization_id' => $organizationId, 'phone_normalized' => preg_replace('/\D+/', '', $phone)],
            [
                'preferred_field_id' => $fieldId,
                'name' => $name,
                'phone' => $phone,
                'reliability_status' => ReliabilityStatus::Reliable,
                'reliability_score' => 100,
            ],
        );
    }

    private function printCredentials(User $superAdmin, User $owner, User $employee): void
    {
        $this->command?->info('Local test credentials');
        $this->command?->line("Super Admin: {$superAdmin->email} / ".self::PASSWORD);
        $this->command?->line("Owner: {$owner->email} / ".self::PASSWORD);
        $this->command?->line("Employee: {$employee->email} / ".self::PASSWORD);
    }
}
