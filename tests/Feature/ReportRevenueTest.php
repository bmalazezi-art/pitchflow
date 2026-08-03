<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FootballField;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportRevenueTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_reports_separate_booked_and_collected_revenue_for_unpaid_and_paid_reservations(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-22 10:00:00', 'Europe/Belgrade'));

        $organization = Organization::factory()->create(['timezone' => 'Europe/Pristina']);
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create(['price_per_hour' => 20]);
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);
        $start = CarbonImmutable::now(Timezones::resolve($organization->timezone))->setTime(18, 0);

        $payload = [
            'customer_name' => 'Revenue Customer',
            'customer_phone' => '+38344123456',
            'football_field_id' => $field->id,
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->addHour()->format('Y-m-d\TH:i'),
            'payment_status' => 'unpaid',
        ];

        $this->actingAs($employee)->post(route('reservations.store'), $payload)->assertRedirect();

        $reservation = Reservation::query()->firstOrFail();
        $this->assertSame('20.00', (string) $reservation->price);
        $this->assertSame('0.00', (string) $reservation->paid_amount);

        $this->actingAs($owner)
            ->get(route('reports', ['period' => 'today']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('report.reservation_count', 1)
                ->where('report.booked_revenue', 20)
                ->where('report.collected_revenue', 0)
                ->where('report.unpaid_reservations', 1)
                ->where('report.payment_stats.unpaid.booked_total', 20));

        $this->actingAs($employee)->patch(route('reservations.paid', $reservation))->assertRedirect();

        $this->actingAs($owner)
            ->get(route('reports', ['period' => 'today']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('report.reservation_count', 1)
                ->where('report.booked_revenue', 20)
                ->where('report.collected_revenue', 20)
                ->where('report.paid_reservations', 1)
                ->where('report.payment_stats.paid.paid_total', 20));
    }

    public function test_reservation_creation_requires_a_field_price_for_revenue_tracking(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-22 10:00:00', 'Europe/Belgrade'));

        $organization = Organization::factory()->create(['timezone' => 'Europe/Pristina']);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create(['price_per_hour' => 0]);
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);
        $start = CarbonImmutable::now(Timezones::resolve($organization->timezone))->setTime(18, 0);

        $this->actingAs($employee)->post(route('reservations.store'), [
            'customer_name' => 'Missing Price Customer',
            'customer_phone' => '+38344987654',
            'football_field_id' => $field->id,
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->addHour()->format('Y-m-d\TH:i'),
            'payment_status' => 'unpaid',
        ])->assertSessionHasErrors('football_field_id');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reports_show_cancellation_breakdowns_without_counting_active_revenue(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-22 10:00:00', 'Europe/Belgrade'));

        $organization = Organization::factory()->create(['timezone' => 'Europe/Pristina']);
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee, 'name' => 'Reception Staff']);
        $field = FootballField::factory()->for($organization)->create(['price_per_hour' => 20]);
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);
        $start = CarbonImmutable::now(Timezones::resolve($organization->timezone))->setTime(18, 0);

        $this->actingAs($employee)->post(route('reservations.store'), [
            'customer_name' => 'Cancelled Customer',
            'customer_phone' => '+38344123457',
            'football_field_id' => $field->id,
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->addHour()->format('Y-m-d\TH:i'),
            'payment_status' => 'paid',
        ])->assertRedirect();

        $reservation = Reservation::query()->firstOrFail();
        $this->actingAs($employee)->delete(route('reservations.destroy', $reservation), [
            'reason' => 'customer_called',
            'note' => 'Customer called to cancel.',
        ])->assertRedirect();

        $this->actingAs($owner)
            ->get(route('reports', ['period' => 'today']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('report.reservation_count', 0)
                ->where('report.booked_revenue', 0)
                ->where('report.collected_revenue', 0)
                ->where('report.total_cancellations', 1)
                ->where('report.paid_cancelled_revenue', 20)
                ->where('report.correction_requests', 0)
                ->where('report.corrected_reservations', 0)
                ->where('report.cancellations_by_reason.customer_called', 1)
                ->where('report.cancellations_by_employee.Reception Staff', 1));
    }

    public function test_reports_exclude_completed_reservation_corrected_to_cancelled(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-22 10:00:00', 'Europe/Belgrade'));

        $organization = Organization::factory()->create(['timezone' => 'Europe/Pristina']);
        $owner = User::factory()->for($organization)->create(['role' => UserRole::Owner]);
        $employee = User::factory()->for($organization)->create(['role' => UserRole::Employee]);
        $field = FootballField::factory()->for($organization)->create(['price_per_hour' => 20]);
        $employee->assignedFields()->attach($field, ['organization_id' => $organization->id]);
        $start = CarbonImmutable::now(Timezones::resolve($organization->timezone))->setTime(18, 0);

        $this->actingAs($employee)->post(route('reservations.store'), [
            'customer_name' => 'Corrected Customer',
            'customer_phone' => '+38344123458',
            'football_field_id' => $field->id,
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->addHour()->format('Y-m-d\TH:i'),
            'payment_status' => 'unpaid',
        ])->assertRedirect();

        $reservation = Reservation::query()->firstOrFail();
        $this->actingAs($employee)->patch(route('reservations.complete', $reservation))->assertRedirect();
        $this->actingAs($employee)->post(route('reservations.correction-requests.store', $reservation), [
            'reason' => 'completed_by_mistake',
            'action' => 'cancel',
            'note' => 'Customer called and cancelled after it was marked completed by mistake.',
        ])->assertRedirect();

        $this->actingAs($owner)
            ->get(route('reports', ['period' => 'today']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('report.reservation_count', 0)
                ->where('report.booked_revenue', 0)
                ->where('report.total_cancellations', 1)
                ->where('report.correction_requests', 1)
                ->where('report.corrected_reservations', 1)
                ->where('report.cancellations_by_reason.correction_cancel', 1));
    }
}
