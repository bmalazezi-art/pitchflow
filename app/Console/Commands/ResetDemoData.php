<?php

namespace App\Console\Commands;

use App\Enums\FieldStatus;
use App\Enums\OrganizationStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\AnalyticsEvent;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\FootballField;
use App\Models\OperatingHour;
use App\Models\OperatingHourOverride;
use App\Models\Organization;
use App\Models\OrganizationAdminNote;
use App\Models\OrganizationStatusHistory;
use App\Models\Reservation;
use App\Models\ReservationCorrectionRequest;
use App\Models\ReservationSlot;
use App\Models\Subscription;
use App\Models\SupportRequest;
use App\Models\User;
use App\Models\WaitingListRequest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ResetDemoData extends Command
{
    protected $signature = 'pitchflow:reset-demo-data {--force : Delete old demo data and rebuild without asking for confirmation}';

    protected $description = 'Safely reset PitchFlow local data to clean professional demo launch data while keeping Super Admin users.';

    /** @var array<string, int> */
    private array $summary = [
        'deleted_organizations' => 0,
        'deleted_users' => 0,
        'deleted_reservations' => 0,
        'organizations' => 0,
        'fields' => 0,
        'customers' => 0,
        'reservations' => 0,
        'waiting_list_requests' => 0,
        'analytics_events' => 0,
    ];

    public function handle(): int
    {
        $superAdminCount = User::withTrashed()
            ->where('role', UserRole::SuperAdmin->value)
            ->count();

        if ($superAdminCount === 0) {
            $this->error('No Super Admin user was found. Aborting so you do not lose admin access.');

            return self::FAILURE;
        }

        $this->warn('This will delete old demo/test organizations, owners, employees, fields, reservations, customers, waiting lists, support requests, analytics events, and organization activity logs.');
        $this->line('It will NOT delete Super Admin users, cities, migrations, cache tables, roles/permissions, or system settings.');

        if (! $this->option('force') && ! $this->confirm('Continue and rebuild clean PitchFlow demo data?', false)) {
            $this->info('Cancelled. No data was changed.');

            return self::SUCCESS;
        }

        mt_srand(260803);

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () {
                $this->cleanOldData();
                $demo = $this->createOrganizationsAndFields();
                $this->createReservations($demo);
                $this->createWaitingListRequests();
                $this->createAnalyticsEvents();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->newLine();
        $this->info('PitchFlow demo launch data reset completed successfully.');
        $this->table(['Item', 'Count'], [
            ['Old organizations deleted', $this->summary['deleted_organizations']],
            ['Old owner/employee users deleted', $this->summary['deleted_users']],
            ['Old reservations deleted', $this->summary['deleted_reservations']],
            ['Super Admin users kept safely', $superAdminCount],
            ['Demo organizations created', $this->summary['organizations']],
            ['Demo football fields created', $this->summary['fields']],
            ['Demo customers created', $this->summary['customers']],
            ['Demo reservations created', $this->summary['reservations']],
            ['Waiting list requests created', $this->summary['waiting_list_requests']],
            ['Analytics events created', $this->summary['analytics_events']],
        ]);

        $this->line('Command to run again: php artisan pitchflow:reset-demo-data');

        return self::SUCCESS;
    }

    private function cleanOldData(): void
    {
        $superAdminIds = User::withTrashed()
            ->where('role', UserRole::SuperAdmin->value)
            ->pluck('id');

        $this->summary['deleted_organizations'] = Organization::withTrashed()->count();
        $this->summary['deleted_users'] = User::withTrashed()
            ->where('role', '!=', UserRole::SuperAdmin->value)
            ->count();
        $this->summary['deleted_reservations'] = Reservation::withTrashed()->count();

        DB::table('sessions')
            ->when($superAdminIds->isNotEmpty(), fn ($query) => $query->whereNotIn('user_id', $superAdminIds))
            ->delete();
        DB::table('password_reset_tokens')->delete();
        DB::table('employee_field_assignments')->delete();
        DB::table('activity_logs')->delete();
        DB::table('analytics_events')->delete();

        WaitingListRequest::query()->delete();
        ReservationCorrectionRequest::query()->delete();
        ReservationSlot::query()->delete();
        CustomerNote::withTrashed()->forceDelete();
        Reservation::withTrashed()->forceDelete();
        Customer::withTrashed()->forceDelete();
        OperatingHourOverride::query()->delete();
        OperatingHour::query()->delete();
        OrganizationStatusHistory::query()->delete();
        OrganizationAdminNote::query()->delete();
        SupportRequest::query()->delete();
        Subscription::query()->delete();
        FootballField::withTrashed()->forceDelete();
        Organization::withTrashed()->forceDelete();
        User::withTrashed()
            ->where('role', '!=', UserRole::SuperAdmin->value)
            ->forceDelete();
    }

    /**
     * @return array<int, array{organization: Organization, fields: array<int, FootballField>, customers: array<int, Customer>}>
     */
    private function createOrganizationsAndFields(): array
    {
        $approvedAt = CarbonImmutable::now('Europe/Belgrade')->subDays(3);
        $cityIds = $this->ensureDemoCities();
        $organizations = [
            ['city' => 'Prishtinë', 'name' => 'Prishtinë Demo Arena', 'phone' => '044 000 101', 'address' => 'Demo Sports Street 12, Prishtinë', 'fields' => ['Main Pitch', 'Small Pitch'], 'prices' => [35, 30]],
            ['city' => 'Prizren', 'name' => 'Prizren Demo Football Center', 'phone' => '044 000 102', 'address' => 'Demo Riverside Road 8, Prizren', 'fields' => ['Main Pitch', 'North Pitch'], 'prices' => [35, 28]],
            ['city' => 'Prizren', 'name' => 'Prizren Green Field Demo', 'phone' => '045 000 201', 'address' => 'Demo Field Avenue 4, Prizren', 'fields' => ['Outdoor Pitch'], 'prices' => [30]],
            ['city' => 'Ferizaj', 'name' => 'Ferizaj City Pitch Demo', 'phone' => '049 000 301', 'address' => 'Demo Stadium Lane 6, Ferizaj', 'fields' => ['Main Pitch', 'South Pitch'], 'prices' => [32, 28]],
            ['city' => 'Ferizaj', 'name' => 'Ferizaj Green Pitch Demo', 'phone' => '044 000 103', 'address' => 'Demo Green Road 15, Ferizaj', 'fields' => ['Indoor Pitch'], 'prices' => [38]],
            ['city' => 'Gjilan', 'name' => 'Gjilan Demo Arena', 'phone' => '045 000 202', 'address' => 'Demo Arena Boulevard 10, Gjilan', 'fields' => ['Main Pitch', 'Small Pitch'], 'prices' => [34, 27]],
            ['city' => 'Gjilan', 'name' => 'Gjilan City Pitch Demo', 'phone' => '049 000 302', 'address' => 'Demo City Road 19, Gjilan', 'fields' => ['Outdoor Pitch'], 'prices' => [29]],
            ['city' => 'Pejë', 'name' => 'Pejë Sport Field Demo', 'phone' => '044 000 104', 'address' => 'Demo Mountain Street 7, Pejë', 'fields' => ['Main Pitch', 'North Pitch'], 'prices' => [36, 30]],
            ['city' => 'Pejë', 'name' => 'Pejë Green Field Demo', 'phone' => '045 000 203', 'address' => 'Demo Sports Road 3, Pejë', 'fields' => ['Indoor Pitch'], 'prices' => [40]],
        ];

        $created = [];

        foreach ($organizations as $index => $demoOrganization) {
            $organization = Organization::create([
                'city_id' => $cityIds[$demoOrganization['city']],
                'name' => $demoOrganization['name'],
                'slug' => Str::slug($demoOrganization['name']),
                'email' => 'demo+'.Str::slug($demoOrganization['name']).'@pitchflow.app',
                'phone' => $demoOrganization['phone'],
                'address' => $demoOrganization['address'],
                'amenities' => ['parking', 'lighting', $index % 2 === 0 ? 'cafe' : 'showers'],
                'status' => OrganizationStatus::Approved,
                'subscription_plan' => 'demo',
                'number_of_fields' => count($demoOrganization['fields']),
                'timezone' => 'Europe/Belgrade',
                'currency' => 'EUR',
                'locale' => 'sq',
                'cancellation_window_minutes' => 120,
                'approved_at' => $approvedAt->addHours($index),
                'featured_from' => $approvedAt,
                'featured_until' => $approvedAt->addDays(30),
            ]);
            $this->summary['organizations']++;

            Subscription::create([
                'organization_id' => $organization->id,
                'plan_name' => 'Demo launch',
                'price' => 0,
                'billing_cycle' => 'monthly',
                'status' => 'trial',
                'started_at' => now(),
                'expires_at' => now()->addMonth(),
            ]);

            OrganizationAdminNote::create([
                'organization_id' => $organization->id,
                'user_id' => null,
                'note' => 'Demo data organization for launch preview. Not a real football field business.',
            ]);

            $fields = [];
            foreach ($demoOrganization['fields'] as $fieldIndex => $fieldName) {
                $field = FootballField::create([
                    'organization_id' => $organization->id,
                    'city_id' => $organization->city_id,
                    'name' => $fieldName,
                    'slug' => Str::slug($fieldName),
                    'address' => $organization->address,
                    'status' => FieldStatus::Active,
                    'price_per_hour' => $demoOrganization['prices'][$fieldIndex],
                    'opening_time' => '16:00',
                    'closing_time' => '00:00',
                ]);
                $fields[] = $field;
                $this->summary['fields']++;

                foreach (range(0, 6) as $dayOfWeek) {
                    OperatingHour::create([
                        'organization_id' => $organization->id,
                        'football_field_id' => $field->id,
                        'day_of_week' => $dayOfWeek,
                        'opening_time' => '16:00',
                        'closing_time' => '00:00',
                        'is_closed' => false,
                    ]);
                }
            }

            $customers = $this->createCustomersFor($organization, $fields);
            $created[] = compact('organization', 'fields', 'customers');
        }

        return $created;
    }

    /** @return array<string, int> */
    private function ensureDemoCities(): array
    {
        $names = ['Prishtinë', 'Prizren', 'Ferizaj', 'Gjilan', 'Pejë'];
        $ids = [];

        foreach ($names as $name) {
            $ids[$name] = City::updateOrCreate(
                ['name' => $name, 'country' => 'XK'],
                ['is_active' => true],
            )->id;
        }

        return $ids;
    }

    /**
     * @param array<int, FootballField> $fields
     * @return array<int, Customer>
     */
    private function createCustomersFor(Organization $organization, array $fields): array
    {
        $customers = [
            ['Demo Client 1', '044 000 101'],
            ['Demo Client 2', '044 000 102'],
            ['Demo Client 3', '044 000 103'],
            ['Demo Client 4', '044 000 104'],
            ['Test Customer 1', '045 000 201'],
            ['Test Customer 2', '045 000 202'],
            ['Sample Player 1', '049 000 301'],
            ['Sample Player 2', '049 000 302'],
        ];

        return collect($customers)->map(function (array $customer, int $index) use ($organization, $fields) {
            $model = Customer::create([
                'organization_id' => $organization->id,
                'preferred_field_id' => $fields[$index % count($fields)]->id,
                'name' => $customer[0],
                'phone' => $customer[1],
                'phone_normalized' => $this->normalizePhone($customer[1]),
                'reliability_status' => $index === 6 ? 'needs_attention' : 'reliable',
                'reliability_status_manual' => false,
                'reliability_score' => $index === 6 ? 72 : 95 - ($index % 4),
            ]);

            if ($index < 2) {
                CustomerNote::create([
                    'organization_id' => $organization->id,
                    'customer_id' => $model->id,
                    'user_id' => null,
                    'note' => $index === 0
                        ? 'Demo note: usually prefers evening slots.'
                        : 'Demo note: calls ahead before arriving.',
                ]);
            }

            $this->summary['customers']++;

            return $model;
        })->all();
    }

    /**
     * @param array<int, array{organization: Organization, fields: array<int, FootballField>, customers: array<int, Customer>}> $demo
     */
    private function createReservations(array $demo): void
    {
        $now = CarbonImmutable::now('Europe/Belgrade');
        $startDate = $now->startOfDay();
        $endDate = CarbonImmutable::create(2026, 8, 14, 0, 0, 0, 'Europe/Belgrade');
        $bookingWeights = [
            16 => 22,
            17 => 28,
            18 => 58,
            19 => 68,
            20 => 64,
            21 => 50,
            22 => 34,
            23 => 24,
        ];
        $paymentCycle = [PaymentStatus::Paid, PaymentStatus::Unpaid, PaymentStatus::Partial, PaymentStatus::Unpaid];

        foreach ($demo as $organizationDemo) {
            foreach ($organizationDemo['fields'] as $fieldIndex => $field) {
                for ($date = $startDate; $date->lessThanOrEqualTo($endDate); $date = $date->addDay()) {
                    foreach ($bookingWeights as $hour => $weight) {
                        $slotStart = $date->setTime($hour, 0);
                        $slotEnd = $slotStart->addHour();

                        if (mt_rand(1, 100) > $weight) {
                            continue;
                        }

                        $customer = $organizationDemo['customers'][($date->day + $hour + $fieldIndex) % count($organizationDemo['customers'])];
                        $status = $this->reservationStatusFor($slotStart, $slotEnd, $now);
                        $paymentStatus = $status === ReservationStatus::Cancelled
                            ? PaymentStatus::Unpaid
                            : $paymentCycle[($date->day + $hour + $fieldIndex) % count($paymentCycle)];
                        $total = (float) $field->price_per_hour;
                        $paidAmount = match ($paymentStatus) {
                            PaymentStatus::Paid => $total,
                            PaymentStatus::Partial => round($total / 2, 2),
                            PaymentStatus::Unpaid => 0,
                        };

                        $reservation = Reservation::create([
                            'organization_id' => $organizationDemo['organization']->id,
                            'football_field_id' => $field->id,
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->name,
                            'customer_phone' => $customer->phone,
                            'starts_at' => $slotStart->utc(),
                            'ends_at' => $slotEnd->utc(),
                            'status' => $status,
                            'payment_status' => $paymentStatus,
                            'price' => $total,
                            'paid_amount' => $status === ReservationStatus::Cancelled ? 0 : $paidAmount,
                            'currency' => 'EUR',
                            'is_walk_in' => ($hour + $fieldIndex) % 5 === 0,
                            'notes' => ($hour + $date->day) % 6 === 0 ? 'Demo booking note: paid cash at reception.' : null,
                            'cancellation_reason' => $status === ReservationStatus::Cancelled ? 'Customer called to cancel' : null,
                            'previous_status' => $status === ReservationStatus::Cancelled ? ReservationStatus::Confirmed->value : null,
                            'cancellation_note' => $status === ReservationStatus::Cancelled ? 'Demo cancellation note.' : null,
                            'cancelled_at' => $status === ReservationStatus::Cancelled ? $slotStart->subHours(4)->utc() : null,
                        ]);

                        if ($status->blocksAvailability()) {
                            ReservationSlot::create([
                                'organization_id' => $reservation->organization_id,
                                'football_field_id' => $reservation->football_field_id,
                                'reservation_id' => $reservation->id,
                                'starts_at' => $reservation->starts_at,
                            ]);
                        }

                        ActivityLog::withoutEvents(fn () => ActivityLog::create([
                            'organization_id' => $reservation->organization_id,
                            'user_id' => null,
                            'action' => 'demo_reservation_created',
                            'entity_type' => Reservation::class,
                            'entity_id' => $reservation->id,
                            'description' => 'Demo reservation created for launch preview.',
                            'properties' => [
                                'status' => $status->value,
                                'payment_status' => $paymentStatus->value,
                                'demo_data' => true,
                            ],
                        ]));

                        $this->summary['reservations']++;
                    }
                }
            }
        }

        $this->refreshCustomerStats();
    }

    private function reservationStatusFor(CarbonImmutable $slotStart, CarbonImmutable $slotEnd, CarbonImmutable $now): ReservationStatus
    {
        if ($slotEnd->lessThanOrEqualTo($now)) {
            $roll = mt_rand(1, 100);

            return match (true) {
                $roll <= 8 => ReservationStatus::Cancelled,
                $roll <= 12 => ReservationStatus::NoShow,
                default => ReservationStatus::Completed,
            };
        }

        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 10 => ReservationStatus::Cancelled,
            $roll <= 30 => ReservationStatus::Pending,
            default => ReservationStatus::Confirmed,
        };
    }

    private function refreshCustomerStats(): void
    {
        Customer::query()->each(function (Customer $customer) {
            $reservations = Reservation::query()
                ->where('customer_id', $customer->id)
                ->get(['status', 'starts_at']);

            $customer->update([
                'total_reservations' => $reservations->count(),
                'completed_reservations' => $reservations->where('status', ReservationStatus::Completed)->count(),
                'cancelled_reservations' => $reservations->where('status', ReservationStatus::Cancelled)->count(),
                'late_cancellations' => $reservations->where('status', ReservationStatus::LateCancelled)->count(),
                'no_shows' => $reservations->where('status', ReservationStatus::NoShow)->count(),
                'last_visit_at' => $reservations
                    ->where('status', ReservationStatus::Completed)
                    ->sortByDesc('starts_at')
                    ->first()?->starts_at,
            ]);
        });
    }

    private function createWaitingListRequests(): void
    {
        $reservations = Reservation::query()
            ->with('footballField')
            ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
            ->where('starts_at', '>', CarbonImmutable::now('UTC'))
            ->orderBy('starts_at')
            ->limit(12)
            ->get();

        $waitingCustomers = [
            ['Demo Client 1', '044 000 101'],
            ['Sample Player 1', '049 000 301'],
            ['Test Customer 2', '045 000 202'],
        ];

        foreach ($reservations as $index => $reservation) {
            $count = $index % 3 === 0 ? 2 : 1;

            for ($i = 0; $i < $count; $i++) {
                $customer = $waitingCustomers[($index + $i) % count($waitingCustomers)];
                WaitingListRequest::create([
                    'organization_id' => $reservation->organization_id,
                    'football_field_id' => $reservation->football_field_id,
                    'reservation_id' => $reservation->id,
                    'date' => $reservation->starts_at->timezone('Europe/Belgrade')->toDateString(),
                    'start_time' => $reservation->starts_at->timezone('Europe/Belgrade')->format('H:i:s'),
                    'end_time' => $reservation->ends_at->timezone('Europe/Belgrade')->format('H:i:s'),
                    'customer_name' => $customer[0],
                    'phone' => $customer[1],
                    'email' => null,
                    'note' => $i === 0 ? 'Demo waiting list request.' : null,
                    'status' => 'waiting',
                    'expires_at' => $reservation->ends_at->addDay(),
                ]);
                $this->summary['waiting_list_requests']++;
            }
        }
    }

    private function createAnalyticsEvents(): void
    {
        $organizations = Organization::query()->with(['city', 'footballFields'])->get();
        $cities = City::query()->whereIn('name', ['Prishtinë', 'Prizren', 'Ferizaj', 'Gjilan', 'Pejë'])->get()->keyBy('name');
        $start = CarbonImmutable::now('Europe/Belgrade')->subDays(9)->startOfDay();

        foreach (range(0, 9) as $dayOffset) {
            $date = $start->addDays($dayOffset);
            foreach (range(1, 18 + ($dayOffset % 5) * 3) as $eventIndex) {
                $city = $cities->values()[($eventIndex + $dayOffset) % $cities->count()];
                $organization = $organizations->where('city_id', $city->id)->values()->first();
                $field = $organization?->footballFields->first();
                $eventType = $this->analyticsTypeFor($eventIndex);

                AnalyticsEvent::create([
                    'organization_id' => in_array($eventType, ['business_view', 'field_view', 'availability_slot_view', 'call_click'], true) ? $organization?->id : null,
                    'football_field_id' => in_array($eventType, ['field_view', 'availability_slot_view', 'call_click'], true) ? $field?->id : null,
                    'city_id' => in_array($eventType, ['city_selected', 'availability_search', 'business_view', 'field_view', 'availability_slot_view', 'call_click'], true) ? $city->id : null,
                    'event_type' => $eventType,
                    'visitor_id' => 'demo-visitor-'.(($eventIndex + $dayOffset) % 24),
                    'ip_hash' => hash('sha256', 'demo-ip-'.$eventIndex),
                    'user_agent_hash' => hash('sha256', 'demo-agent-'.$dayOffset),
                    'metadata' => ['demo_data' => true],
                    'created_at' => $date->addMinutes($eventIndex * 23)->utc(),
                    'updated_at' => $date->addMinutes($eventIndex * 23)->utc(),
                ]);
                $this->summary['analytics_events']++;
            }
        }
    }

    private function analyticsTypeFor(int $eventIndex): string
    {
        return match (true) {
            $eventIndex % 11 === 0 => 'register_business_click',
            $eventIndex % 7 === 0 => 'call_click',
            $eventIndex % 5 === 0 => 'business_view',
            $eventIndex % 4 === 0 => 'field_view',
            $eventIndex % 3 === 0 => 'availability_search',
            $eventIndex % 2 === 0 => 'city_selected',
            default => 'public_home_view',
        };
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', trim($phone)) ?: $phone;
    }
}
