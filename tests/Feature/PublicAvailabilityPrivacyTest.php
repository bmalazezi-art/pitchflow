<?php

namespace Tests\Feature;

use App\Enums\FieldStatus;
use App\Enums\OrganizationStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\Customer;
use App\Models\FootballField;
use App\Models\OperatingHour;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Timezones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicAvailabilityPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_public_page_does_not_expose_private_customer_fields(): void
    {
        $organization = Organization::factory()->create();
        FootballField::factory()->for($organization)->create(['name' => 'Public Field', 'city_id' => null]);

        $this->get('/?city='.$organization->city_id)->assertOk()
            ->assertSee('Public Field')
            ->assertDontSee('customer_phone')
            ->assertDontSee('payment_status')
            ->assertDontSee('reliability_status');
    }

    public function test_public_directory_only_contains_approved_businesses_with_public_non_deleted_fields_in_the_selected_city(): void
    {
        Carbon::setTestNow('2026-06-29 12:00:00');
        $city = City::factory()->create(['name' => 'Prizren']);
        $otherCity = City::factory()->create(['name' => 'Pejë']);

        $visible = Organization::factory()->for($city)->create([
            'name' => 'Visible Arena',
            'amenities' => ['parking', 'showers'],
            'approved_at' => now()->subDays(10),
        ]);
        FootballField::factory()->for($visible)->create(['city_id' => $city->id, 'name' => 'Visible Pitch']);

        $pending = Organization::factory()->for($city)->create(['status' => OrganizationStatus::Pending, 'approved_at' => null]);
        FootballField::factory()->for($pending)->create(['city_id' => $city->id]);

        $maintenance = Organization::factory()->for($city)->create(['name' => 'Maintenance Arena']);
        FootballField::factory()->for($maintenance)->create(['city_id' => $city->id, 'status' => FieldStatus::Maintenance]);

        $missingPhone = Organization::factory()->for($city)->create(['name' => 'Missing Phone Arena', 'phone' => '']);
        FootballField::factory()->for($missingPhone)->create(['city_id' => $city->id]);

        $archived = Organization::factory()->for($city)->create(['name' => 'Archived Arena']);
        FootballField::factory()->for($archived)->create(['city_id' => $city->id])->delete();

        $elsewhere = Organization::factory()->for($otherCity)->create(['name' => 'Other City Arena']);
        FootballField::factory()->for($elsewhere)->create(['city_id' => $otherCity->id]);

        $this->get('/?city='.$city->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('businesses', 2)
            ->where('businesses.0.name', 'Maintenance Arena')
            ->where('businesses.0.football_fields.0.status', 'maintenance')
            ->where('businesses.1.name', 'Visible Arena')
            ->where('businesses.1.is_verified', true)
            ->where('businesses.1.is_new', true)
            ->where('businesses.1.amenities', ['parking', 'showers'])
            ->has('businesses.1.football_fields', 1)
            ->where('businesses.1.football_fields.0.name', 'Visible Pitch')
            ->where('businesses.1.operating_status.is_open', true)
            ->has('recentBusinesses', 2)
            ->where('recentBusinesses.0.name', 'Maintenance Arena')
            ->has('popularCities', 2)
            ->where('statistics.football_fields', 3)
            ->where('statistics.cities', 2)
            ->where('statistics.registered_businesses', 6)
            ->where('statistics.verified_businesses', 5)
        );
    }

    public function test_closed_and_maintenance_fields_stay_public_but_do_not_generate_slots(): void
    {
        $city = City::factory()->create(['name' => 'Ferizaj']);
        $closed = Organization::factory()->for($city)->create(['name' => 'Alpha Closed Arena']);
        $closedField = FootballField::factory()->for($closed)->create([
            'city_id' => $city->id,
            'name' => 'Closed Pitch',
            'status' => FieldStatus::Closed,
        ]);
        $maintenance = Organization::factory()->for($city)->create(['name' => 'Beta Maintenance Arena']);
        $maintenanceField = FootballField::factory()->for($maintenance)->create([
            'city_id' => $city->id,
            'name' => 'Maintenance Pitch',
            'status' => FieldStatus::Maintenance,
        ]);

        $this->get('/?city='.$city->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('businesses', 2)
            ->where('businesses.0.name', 'Alpha Closed Arena')
            ->where('businesses.0.football_fields.0.status', 'closed')
            ->where('businesses.1.name', 'Beta Maintenance Arena')
            ->where('businesses.1.football_fields.0.status', 'maintenance')
        );

        $this->get('/?'.http_build_query([
            'city' => $city->id,
            'field' => $closedField->id,
            'date' => '2026-08-09',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('pitchAvailability.0.field.status', 'closed')
            ->has('pitchAvailability.0.slots', 0)
        );

        $this->get('/?'.http_build_query([
            'city' => $city->id,
            'field' => $maintenanceField->id,
            'date' => '2026-08-09',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('pitchAvailability.0.field.status', 'maintenance')
            ->has('pitchAvailability.0.slots', 0)
        );
    }

    public function test_closed_weekday_public_availability_shows_field_with_no_slots(): void
    {
        $city = City::factory()->create(['name' => 'Gjakovë']);
        $organization = Organization::factory()->for($city)->create(['timezone' => 'Europe/Pristina']);
        $field = FootballField::factory()->for($organization)->create([
            'city_id' => $city->id,
            'name' => 'Sunday Closed Pitch',
            'opening_time' => '16:00',
            'closing_time' => '00:00',
        ]);
        OperatingHour::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'day_of_week' => 0,
            'opening_time' => '16:00',
            'closing_time' => '00:00',
            'is_closed' => true,
        ]);

        $this->get('/?'.http_build_query([
            'city' => $city->id,
            'field' => $field->id,
            'date' => '2026-08-09',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('pitchAvailability.0.field.name', 'Sunday Closed Pitch')
            ->has('pitchAvailability.0.slots', 0)
        );
    }

    public function test_homepage_recently_added_is_limited_to_six_public_businesses(): void
    {
        Carbon::setTestNow('2026-08-03 12:00:00');
        $city = City::factory()->create(['name' => 'Prishtinë']);

        foreach (range(1, 7) as $index) {
            $organization = Organization::factory()->for($city)->create([
                'name' => "Demo Public Arena {$index}",
                'approved_at' => now()->subDays(7 - $index),
            ]);
            FootballField::factory()->for($organization)->create(['city_id' => $city->id]);
        }

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('recentBusinesses', 6)
            ->where('recentBusinesses.0.name', 'Demo Public Arena 7')
        );
    }

    public function test_public_football_fields_listing_filters_and_exposes_only_safe_public_data(): void
    {
        $city = City::factory()->create(['name' => 'Ferizaj']);
        $otherCity = City::factory()->create(['name' => 'Gjilan']);
        $visible = Organization::factory()->for($city)->create(['name' => 'Demo Visible Pitch']);
        FootballField::factory()->for($visible)->create(['city_id' => $city->id, 'name' => 'Main Pitch']);
        $pending = Organization::factory()->for($city)->create(['name' => 'Pending Private Pitch', 'status' => OrganizationStatus::Pending]);
        FootballField::factory()->for($pending)->create(['city_id' => $city->id]);
        $other = Organization::factory()->for($otherCity)->create(['name' => 'Other City Pitch']);
        FootballField::factory()->for($other)->create(['city_id' => $otherCity->id]);
        $customer = Customer::factory()->for($visible)->create(['name' => 'Private Customer']);
        Reservation::create([
            'organization_id' => $visible->id,
            'football_field_id' => $visible->footballFields()->first()->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'price' => 40,
            'paid_amount' => 40,
            'currency' => 'EUR',
        ]);

        $this->get('/football-fields?city='.$city->id.'&search=Visible')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Fields')
                ->has('businesses', 1)
                ->where('businesses.0.name', 'Demo Visible Pitch')
                ->where('businesses.0.city.name', 'Ferizaj')
                ->has('businesses.0.football_fields', 1)
                ->where('filters.city', $city->id)
                ->where('filters.search', 'Visible')
            )
            ->assertDontSee('Private Customer')
            ->assertDontSee('customer_phone')
            ->assertDontSee('payment_status')
            ->assertDontSee('Pending Private Pitch')
            ->assertDontSee('Other City Pitch');
    }

    public function test_public_directory_orders_featured_then_new_then_alphabetical(): void
    {
        Carbon::setTestNow('2026-06-29 12:00:00');
        $city = City::factory()->create(['name' => 'Prishtinë']);
        $old = Organization::factory()->for($city)->create(['name' => 'Alpha Arena', 'approved_at' => now()->subDays(60)]);
        $new = Organization::factory()->for($city)->create(['name' => 'Zulu New Arena', 'approved_at' => now()->subDays(5)]);
        $featured = Organization::factory()->for($city)->create([
            'name' => 'Featured Arena',
            'approved_at' => now()->subDays(90),
            'featured_from' => now()->subDay(),
            'featured_until' => now()->addDay(),
        ]);
        foreach ([$old, $new, $featured] as $organization) {
            FootballField::factory()->for($organization)->create(['city_id' => $city->id]);
        }

        $this->get('/?city='.$city->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('businesses.0.name', 'Featured Arena')
            ->where('businesses.1.name', 'Zulu New Arena')
            ->where('businesses.1.is_new', true)
            ->where('businesses.2.name', 'Alpha Arena')
            ->where('businesses.2.is_new', false)
        );
    }

    public function test_admin_approval_automatically_publishes_an_eligible_business(): void
    {
        $city = City::factory()->create(['name' => 'Ferizaj']);
        $business = Organization::factory()->for($city)->create([
            'name' => 'Fresh Football Center',
            'status' => OrganizationStatus::Pending,
            'approved_at' => null,
        ]);
        FootballField::factory()->for($business)->create(['city_id' => $city->id]);
        $platform = Organization::factory()->create();
        $admin = User::factory()->for($platform)->create(['role' => UserRole::SuperAdmin]);

        $this->get('/?city='.$city->id)->assertDontSee('Fresh Football Center');
        $this->actingAs($admin)->patch("/admin/organizations/{$business->id}", ['status' => 'approved'])->assertRedirect();

        $this->assertNotNull($business->refresh()->approved_at);
        $this->get('/?city='.$city->id)->assertSee('Fresh Football Center');
    }

    public function test_public_legal_pages_are_available(): void
    {
        $this->get('/privacy')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Legal')
            ->where('document', 'privacy'));
        $this->get('/terms')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Legal')
            ->where('document', 'terms'));
    }

    public function test_city_selector_uses_kosovo_priority_order_and_hides_placeholder_options(): void
    {
        foreach ([
            'Shtime',
            'Prizren',
            'Gjithë Kosovën',
            'Prishtinë',
            'Viti',
            'Ferizaj',
            'Jashtë Vendit',
            'Gjilan',
            'Pejë',
            'Gjakovë',
            'Mitrovicë',
            'Vushtrri',
            'Podujevë',
            'Deçan',
            'Drenas',
            'Fushë Kosovë',
            'Klinë',
            'Lipjan',
            'Malishevë',
            'Obiliq',
            'Kamenicë',
            'Suharekë',
        ] as $name) {
            City::factory()->create(['name' => $name, 'country' => 'XK', 'is_active' => true]);
        }

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('cities.0.name', 'Prishtinë')
            ->where('cities.1.name', 'Prizren')
            ->where('cities.2.name', 'Ferizaj')
            ->where('cities.3.name', 'Gjilan')
            ->where('cities.4.name', 'Pejë')
            ->where('cities.5.name', 'Gjakovë')
            ->where('cities.6.name', 'Mitrovicë')
            ->where('cities.7.name', 'Vushtrri')
            ->where('cities.8.name', 'Podujevë')
            ->where('cities.9.name', 'Deçan')
            ->where('cities.10.name', 'Drenas')
            ->where('cities.11.name', 'Fushë Kosovë')
            ->where('cities.12.name', 'Klinë')
            ->where('cities.13.name', 'Lipjan')
            ->where('cities.14.name', 'Malishevë')
            ->where('cities.15.name', 'Obiliq')
            ->where('cities.16.name', 'Shtime')
            ->where('cities.17.name', 'Suharekë')
            ->where('cities.18.name', 'Viti')
            ->missing('cities.19'));
    }

    public function test_current_hour_slot_stays_available_until_it_ends_in_business_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 14:30:00', 'Europe/Belgrade'));
        $city = City::factory()->create(['name' => 'Ferizaj']);
        $organization = Organization::factory()->for($city)->create([
            'timezone' => 'Europe/Pristina',
            'name' => 'Arena Ferizaj',
        ]);
        $field = FootballField::factory()->for($organization)->create([
            'city_id' => $city->id,
            'name' => 'Arena',
            'opening_time' => '12:00',
            'closing_time' => '16:00',
        ]);

        $this->get('/?'.http_build_query([
            'city' => $city->id,
            'field' => $field->id,
            'date' => '2026-07-22',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Availability')
            ->where('pitchAvailability.0.slots.0.label', '12:00–13:00')
            ->where('pitchAvailability.0.slots.0.status', 'past')
            ->where('pitchAvailability.0.slots.1.label', '13:00–14:00')
            ->where('pitchAvailability.0.slots.1.status', 'past')
            ->where('pitchAvailability.0.slots.2.label', '14:00–15:00')
            ->where('pitchAvailability.0.slots.2.status', 'current')
            ->where('pitchAvailability.0.slots.3.label', '15:00–16:00')
            ->where('pitchAvailability.0.slots.3.status', 'available')
        );
    }

    public function test_future_public_availability_date_does_not_mark_slots_as_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 14:30:00', 'Europe/Belgrade'));
        $city = City::factory()->create(['name' => 'Prishtinë']);
        $organization = Organization::factory()->for($city)->create(['timezone' => 'Europe/Pristina']);
        $field = FootballField::factory()->for($organization)->create([
            'city_id' => $city->id,
            'opening_time' => '12:00',
            'closing_time' => '16:00',
        ]);

        $this->get('/?'.http_build_query([
            'city' => $city->id,
            'field' => $field->id,
            'date' => '2026-07-23',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Availability')
            ->where('pitchAvailability.0.slots.0.status', 'available')
            ->where('pitchAvailability.0.slots.1.status', 'available')
            ->where('pitchAvailability.0.slots.2.status', 'available')
            ->where('pitchAvailability.0.slots.3.status', 'available')
        );
    }

    public function test_completed_reservation_still_blocks_public_availability_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-24 12:00:00', 'Europe/Belgrade'));
        $city = City::factory()->create(['name' => 'Ferizaj']);
        $organization = Organization::factory()->for($city)->create(['timezone' => 'Europe/Pristina']);
        $field = FootballField::factory()->for($organization)->create([
            'city_id' => $city->id,
            'opening_time' => '12:00',
            'closing_time' => '23:00',
        ]);
        $customer = Customer::factory()->for($organization)->create();
        $start = Carbon::parse('2026-07-25 18:00:00', Timezones::resolve($organization->timezone));
        $reservation = Reservation::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'starts_at' => $start->utc(),
            'ends_at' => $start->copy()->addHour()->utc(),
            'status' => ReservationStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'price' => 20,
            'paid_amount' => 20,
            'currency' => 'EUR',
        ]);

        $this->get('/?'.http_build_query([
            'city' => $city->id,
            'field' => $field->id,
            'date' => '2026-07-25',
            'client_now' => '2026-07-24T12:00',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Availability')
            ->where('pitchAvailability.0.slots.6.label', '18:00–19:00')
            ->where('pitchAvailability.0.slots.6.status', 'reserved')
            ->where('pitchAvailability.0.slots.6.reservation_id', $reservation->id)
        );
    }

    public function test_public_availability_uses_client_now_to_avoid_local_server_timezone_drift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-26 23:34:00', 'Europe/Belgrade'));
        $city = City::factory()->create(['name' => 'Gjakovë']);
        $organization = Organization::factory()->for($city)->create(['timezone' => 'Europe/Pristina']);
        $field = FootballField::factory()->for($organization)->create([
            'city_id' => $city->id,
            'opening_time' => '12:00',
            'closing_time' => '23:00',
        ]);

        $this->get('/?'.http_build_query([
            'city' => $city->id,
            'field' => $field->id,
            'date' => '2026-07-26',
            'client_now' => '2026-07-26T14:34',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Availability')
            ->where('pitchAvailability.0.slots.0.status', 'past')
            ->where('pitchAvailability.0.slots.1.status', 'past')
            ->where('pitchAvailability.0.slots.2.status', 'current')
            ->where('pitchAvailability.0.slots.3.label', '15:00–16:00')
            ->where('pitchAvailability.0.slots.3.status', 'available')
            ->where('pitchAvailability.0.slots.10.label', '22:00–23:00')
            ->where('pitchAvailability.0.slots.10.status', 'available')
        );
    }
}
