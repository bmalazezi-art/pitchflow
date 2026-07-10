<?php

namespace Tests\Feature;

use App\Enums\FieldStatus;
use App\Enums\OrganizationStatus;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\User;
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

    public function test_public_directory_only_contains_approved_businesses_with_active_non_deleted_fields_in_the_selected_city(): void
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

        $archived = Organization::factory()->for($city)->create(['name' => 'Archived Arena']);
        FootballField::factory()->for($archived)->create(['city_id' => $city->id])->delete();

        $elsewhere = Organization::factory()->for($otherCity)->create(['name' => 'Other City Arena']);
        FootballField::factory()->for($elsewhere)->create(['city_id' => $otherCity->id]);

        $this->get('/?city='.$city->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('businesses', 1)
            ->where('businesses.0.name', 'Visible Arena')
            ->where('businesses.0.is_verified', true)
            ->where('businesses.0.is_new', true)
            ->where('businesses.0.amenities', ['parking', 'showers'])
            ->has('businesses.0.football_fields', 1)
            ->where('businesses.0.football_fields.0.name', 'Visible Pitch')
            ->where('businesses.0.operating_status.is_open', true)
            ->has('recentBusinesses', 1)
            ->where('recentBusinesses.0.name', 'Visible Arena')
            ->has('popularCities', 2)
            ->where('statistics.football_fields', 2)
            ->where('statistics.cities', 2)
            ->where('statistics.registered_businesses', 5)
            ->where('statistics.verified_businesses', 4)
        );
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
}
