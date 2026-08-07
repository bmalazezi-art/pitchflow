<?php

namespace Tests\Feature;

use App\Enums\ReliabilityStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Timezones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservations_reuse_customers_by_phone_inside_the_organization(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);

        $this->actingAs($employee)->post('/reservations', [
            ...$this->payload($field, $start),
            'customer_name' => 'Bajram Malazezi',
            'customer_phone' => '044 123 456',
        ])->assertRedirect();

        $this->actingAs($employee)->post('/reservations', [
            ...$this->payload($field, $start->copy()->addHour()),
            'customer_name' => 'Bajram M.',
            'customer_phone' => '044 123 456',
        ])->assertRedirect();

        $this->assertSame(1, Customer::query()->where('organization_id', $organization->id)->count());
        $this->assertDatabaseHas('customers', [
            'organization_id' => $organization->id,
            'phone' => '044 123 456',
            'name' => 'Bajram M.',
        ]);
    }

    public function test_same_customer_name_with_different_phones_stays_separate(): void
    {
        [$organization, $employee, $field] = $this->context();
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);

        $this->actingAs($employee)->post('/reservations', [
            ...$this->payload($field, $start),
            'customer_name' => 'Bajram',
            'customer_phone' => '044 123 456',
        ])->assertRedirect();

        $this->actingAs($employee)->post('/reservations', [
            ...$this->payload($field, $start->copy()->addHour()),
            'customer_name' => 'Bajram',
            'customer_phone' => '049 888 777',
        ])->assertRedirect();

        $this->assertSame(2, Customer::query()->where('organization_id', $organization->id)->where('name', 'Bajram')->count());
    }

    public function test_same_customer_name_and_phone_reuses_existing_customer_even_without_normalized_phone(): void
    {
        [$organization, $employee, $field] = $this->context();
        $existing = Customer::factory()->for($organization)->create([
            'name' => 'Bajram Malazezi',
            'phone' => '044 123 456',
            'phone_normalized' => '',
        ]);
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);

        $this->actingAs($employee)->post('/reservations', [
            ...$this->payload($field, $start),
            'customer_name' => 'Bajram Malazezi',
            'customer_phone' => '044 123 456',
        ])->assertRedirect();

        $this->assertSame(1, Customer::query()->where('organization_id', $organization->id)->count());
        $this->assertSame($existing->id, Reservation::query()->firstOrFail()->customer_id);
        $this->assertDatabaseHas('customers', [
            'id' => $existing->id,
            'phone_normalized' => '044123456',
        ]);
    }

    public function test_customers_index_paginates_ten_per_page(): void
    {
        [$organization] = $this->context();
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        Customer::factory()->for($organization)->count(12)->create();

        $this->actingAs($owner)->get('/customers')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('customers.data', 10)
                ->where('customers.per_page', 10)
                ->where('customers.last_page', 2));
    }

    public function test_blocked_customer_cannot_receive_a_new_reservation(): void
    {
        [$organization, $employee, $field] = $this->context();
        $customer = Customer::factory()->for($organization)->create([
            'name' => 'Blocked Customer',
            'phone' => '044 777 888',
            'phone_normalized' => '044777888',
            'reliability_status' => ReliabilityStatus::HighRisk,
            'reliability_status_manual' => true,
        ]);
        $start = now(Timezones::resolve($organization->timezone))->addDay()->setTime(12, 0);

        $this->actingAs($employee)->post('/reservations', [
            ...$this->payload($field, $start),
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
        ])->assertSessionHasErrors('customer_phone');

        $this->assertDatabaseCount('reservations', 0);
    }

    private function context(): array
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create();
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);

        return [$organization, $employee, $field];
    }

    private function payload(FootballField $field, $start): array
    {
        return [
            'customer_name' => 'Customer',
            'customer_phone' => '044 123 456',
            'football_field_id' => $field->id,
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->copy()->addHour()->format('Y-m-d\TH:i'),
            'payment_status' => 'unpaid',
        ];
    }
}
