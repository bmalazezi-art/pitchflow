<?php

namespace App\Http\Controllers;

use App\Enums\FieldStatus;
use App\Models\City;
use App\Models\FootballField;
use App\Models\Organization;
use App\Services\OperatingScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicFieldListingController extends Controller
{
    public function __invoke(Request $request, OperatingScheduleService $operatingSchedule): Response
    {
        $filters = $request->validate([
            'city' => ['nullable', 'integer', 'exists:cities,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date'],
        ]);
        $cityId = isset($filters['city']) ? (int) $filters['city'] : null;
        $search = trim((string) ($filters['search'] ?? ''));
        $selectedDate = $filters['date'] ?? CarbonImmutable::now('Europe/Belgrade')->toDateString();
        $activeFields = fn ($query) => $query
            ->where('status', FieldStatus::Active)
            ->when($cityId, fn ($fieldQuery) => $fieldQuery
                ->where(fn ($cityQuery) => $cityQuery
                    ->where('city_id', $cityId)
                    ->orWhereNull('city_id')));

        $businesses = Organization::query()
            ->eligibleForPublicDirectory()
            ->when($cityId, fn ($query) => $query->publiclyDiscoverable($cityId))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->with([
                'city:id,name',
                'footballFields' => fn ($query) => $activeFields($query)
                    ->orderBy('name')
                    ->select(['id', 'organization_id', 'city_id', 'name', 'address', 'price_per_hour', 'opening_time', 'closing_time']),
                'footballFields.city:id,name',
                'footballFields.organization:id,timezone',
                'footballFields.operatingHours',
                'footballFields.operatingHourOverrides' => fn ($query) => $query
                    ->whereBetween('date', [now()->subDay()->toDateString(), now()->addDays(8)->toDateString()]),
            ])
            ->inPublicDirectoryOrder()
            ->get(['id', 'city_id', 'name', 'phone', 'address', 'number_of_fields', 'currency', 'amenities', 'approved_at'])
            ->each(function (Organization $organization) {
                $organization->setAttribute('is_new', $organization->isNewlyApproved());
                $organization->setAttribute('is_verified', true);
                $organization->makeHidden('approved_at');
            });

        $this->addOperatingStatus($businesses, $operatingSchedule);

        return Inertia::render('Public/Fields', [
            'cities' => City::query()->forSelector()->inKosovoSelectorOrder()->get(['id', 'name']),
            'businesses' => $businesses,
            'filters' => [
                'city' => $cityId,
                'search' => $search,
                'date' => $selectedDate,
            ],
        ]);
    }

    /** @param Collection<int, Organization> $businesses */
    private function addOperatingStatus(Collection $businesses, OperatingScheduleService $schedule): void
    {
        $now = CarbonImmutable::now('UTC');

        foreach ($businesses as $business) {
            $statuses = $business->footballFields->map(fn (FootballField $field) => $schedule->statusAt($field, $now));
            $nextOpening = $statuses->pluck('opens_at')->filter()->sort()->first();
            $business->setAttribute('operating_status', [
                'is_open' => $statuses->contains('is_open', true),
                'opens_at' => $nextOpening?->format('H:i'),
            ]);
            $business->footballFields->each->makeHidden([
                'organization', 'operating_hours', 'operating_hour_overrides',
            ]);
        }
    }
}
