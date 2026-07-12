<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservationFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_reservation_filters_combine_and_summary_uses_selected_period(): void
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create();
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);

        $timezone = Timezones::resolve($organization->timezone);
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $customer = Customer::factory()->for($organization)->create();

        $todayPaid = $this->reservation($organization, $field, $customer, $today->setTime(12, 0), 'confirmed', 'paid');
        $todayUnpaid = $this->reservation($organization, $field, $customer, $today->setTime(13, 0), 'pending', 'unpaid');
        $this->reservation($organization, $field, $customer, $today->setTime(14, 0), 'cancelled', 'unpaid');
        $this->reservation($organization, $field, $customer, $today->addDay()->setTime(12, 0), 'confirmed', 'unpaid');

        $this->actingAs($employee)
            ->get('/reservations?date_filter=today&payment_filter=unpaid')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reservations/Index')
                ->where('summary.total', 3)
                ->where('summary.paid', 1)
                ->where('summary.unpaid', 2)
                ->has('reservations.data', 2)
                ->where('reservations.data.0.payment_status', 'unpaid')
                ->where('filters.date_filter', 'today')
                ->where('filters.payment_filter', 'unpaid'));

        $this->actingAs($employee)
            ->get('/reservations?date_filter=today&payment_filter=paid&status_filter=confirmed')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total', 3)
                ->has('reservations.data', 1)
                ->where('reservations.data.0.id', $todayPaid->id));

        $this->actingAs($employee)
            ->get('/reservations?date_filter=today&payment_filter=unpaid&status_filter=pending')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reservations.data', 1)
                ->where('reservations.data.0.id', $todayUnpaid->id));
    }

    private function reservation(Organization $organization, FootballField $field, Customer $customer, CarbonImmutable $start, string $status, string $paymentStatus): Reservation
    {
        return Reservation::query()->create([
            'organization_id' => $organization->id,
            'football_field_id' => $field->id,
            'customer_id' => $customer->id,
            'customer_name' => "Customer {$start->hour}",
            'customer_phone' => '+38344123456',
            'starts_at' => $start->utc(),
            'ends_at' => $start->addHour()->utc(),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'price' => 40,
            'paid_amount' => $paymentStatus === 'paid' ? 40 : 0,
            'currency' => 'EUR',
        ]);
    }
}
