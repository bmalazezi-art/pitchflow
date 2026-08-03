<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetDemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_demo_reset_keeps_super_admin_and_rebuilds_clean_launch_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 14:30:00', 'Europe/Belgrade'));

        $keptCity = City::factory()->create(['name' => 'Gjakovë']);
        $oldOrganization = Organization::factory()->create(['name' => 'Old Test Arena']);
        $owner = User::factory()->for($oldOrganization)->create(['role' => UserRole::Owner]);
        $field = FootballField::factory()->for($oldOrganization)->create();
        $customer = Customer::factory()->for($oldOrganization)->create();
        $reservation = Reservation::create([
            'organization_id' => $oldOrganization->id,
            'football_field_id' => $field->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'starts_at' => Carbon::parse('2026-08-03 18:00:00', 'Europe/Belgrade')->utc(),
            'ends_at' => Carbon::parse('2026-08-03 19:00:00', 'Europe/Belgrade')->utc(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => 'unpaid',
            'price' => 30,
            'paid_amount' => 0,
            'currency' => 'EUR',
        ]);
        ReservationSlot::create([
            'organization_id' => $oldOrganization->id,
            'football_field_id' => $field->id,
            'reservation_id' => $reservation->id,
            'starts_at' => $reservation->starts_at,
        ]);
        $superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'organization_id' => null,
            'email' => 'admin@example.com',
        ]);

        $this->artisan('pitchflow:reset-demo-data --force')
            ->expectsOutputToContain('PitchFlow demo launch data reset completed successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'id' => $superAdmin->id,
            'role' => UserRole::SuperAdmin->value,
            'email' => 'admin@example.com',
        ]);
        $this->assertSame(0, User::query()->where('role', '!=', UserRole::SuperAdmin->value)->count());
        $this->assertDatabaseHas('cities', ['id' => $keptCity->id, 'name' => 'Gjakovë']);
        $this->assertDatabaseMissing('organizations', ['name' => 'Old Test Arena']);

        $this->assertSame(9, Organization::query()->where('status', OrganizationStatus::Approved)->count());
        $this->assertSame(14, FootballField::query()->count());
        $this->assertGreaterThan(0, Reservation::query()->count());
        $this->assertGreaterThan(0, Customer::query()->count());

        $duplicateSlots = DB::table('reservation_slots')
            ->select('football_field_id', 'starts_at', DB::raw('count(*) as aggregate'))
            ->groupBy('football_field_id', 'starts_at')
            ->having('aggregate', '>', 1)
            ->count();
        $completedWithoutSlots = Reservation::query()
            ->where('status', ReservationStatus::Completed)
            ->whereDoesntHave('slots')
            ->count();
        $cancelledWithSlots = Reservation::query()
            ->where('status', ReservationStatus::Cancelled)
            ->whereHas('slots')
            ->count();

        $this->assertSame(0, $duplicateSlots);
        $this->assertSame(0, $completedWithoutSlots);
        $this->assertSame(0, $cancelledWithSlots);
    }
}
