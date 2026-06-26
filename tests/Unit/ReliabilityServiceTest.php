<?php

namespace Tests\Unit;

use App\Enums\ReliabilityStatus;
use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Services\ReliabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReliabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_no_shows_mark_a_customer_high_risk(): void
    {
        $organization = Organization::factory()->create();
        $field = FootballField::factory()->for($organization)->create();
        $customer = Customer::factory()->for($organization)->create();

        foreach (range(1, 2) as $day) {
            Reservation::create([
                'organization_id' => $organization->id,
                'football_field_id' => $field->id,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'starts_at' => now()->subDays($day)->startOfHour(),
                'ends_at' => now()->subDays($day)->startOfHour()->addHour(),
                'status' => ReservationStatus::NoShow,
                'payment_status' => 'unpaid',
                'currency' => 'EUR',
            ]);
        }

        app(ReliabilityService::class)->recalculate($customer);

        $this->assertSame(ReliabilityStatus::HighRisk, $customer->refresh()->reliability_status);
        $this->assertSame(40, $customer->reliability_score);
        $this->assertSame(2, $customer->no_shows);
    }
}
