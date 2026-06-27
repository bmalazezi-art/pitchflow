<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_dashboard_without_lazy_loading_football_field_organization(): void
    {
        Model::preventLazyLoading();

        $organization = Organization::factory()->create();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $field = FootballField::factory()->for($organization)->create(['price_per_hour' => 45]);
        $customer = Customer::factory()->for($organization)->create();
        $startsAt = CarbonImmutable::now(Timezones::resolve($organization->timezone))->setTime(18, 0)->utc();

        Reservation::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Partial,
            'price' => 45,
            'paid_amount' => 20,
            'currency' => 'EUR',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('metrics.today_reservations', 1)
                ->where('metrics.expected_revenue_today', 45)
                ->where('metrics.today_revenue', 20)
                ->where('metrics.unpaid_reservations', 1)
                ->where('metrics.busiest_field_today', $field->name)
                ->has('metrics.today_timeline', 1)
                ->where('metrics.today_timeline.0.customer_name', $customer->name)
                ->where('metrics.today_timeline.0.football_field.name', $field->name));
    }
}
