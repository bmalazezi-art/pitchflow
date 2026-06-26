<?php

namespace App\Http\Controllers;

use App\Enums\FieldStatus;
use App\Enums\OrganizationStatus;
use App\Models\City;
use App\Models\FootballField;
use App\Models\Organization;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicAvailabilityController extends Controller
{
    public function __invoke(Request $request, AvailabilityService $availability): Response
    {
        $business = null;
        $pitchAvailability = [];

        $businesses = Organization::query()
            ->where('status', OrganizationStatus::Approved)
            ->when($request->filled('city'), fn ($query) => $query
                ->where('city_id', $request->integer('city'))
                ->whereHas('footballFields', fn ($fieldQuery) => $fieldQuery->where('status', FieldStatus::Active)), fn ($query) => $query->whereRaw('1 = 0'))
            ->with([
                'city:id,name',
                'footballFields' => fn ($query) => $query
                    ->where('status', FieldStatus::Active)
                    ->orderBy('name')
                    ->select(['id', 'organization_id', 'city_id', 'name', 'address', 'price_per_hour']),
                'footballFields.city:id,name',
            ])
            ->orderBy('name')
            ->get(['id', 'city_id', 'name', 'phone', 'address', 'number_of_fields', 'currency']);

        if ($request->filled(['city', 'business'])) {
            $business = Organization::query()
                ->whereKey($request->integer('business'))
                ->where('status', OrganizationStatus::Approved)
                ->where('city_id', $request->integer('city'))
                ->whereHas('footballFields', fn ($query) => $query->where('status', FieldStatus::Active))
                ->with([
                    'city:id,name',
                    'footballFields' => fn ($query) => $query
                        ->where('status', FieldStatus::Active)
                        ->orderBy('name')
                        ->select(['id', 'organization_id', 'city_id', 'name', 'address', 'price_per_hour', 'opening_time', 'closing_time']),
                    'footballFields.city:id,name',
                    'footballFields.organization:id,timezone',
                ])
                ->first();

            if ($business) {
                $pitchAvailability = $business->footballFields->map(fn (FootballField $field) => [
                    'field' => $field,
                    'slots' => $availability->slots($field, $request->input('date', now()->toDateString())),
                ])->values();
            }
        } elseif ($request->filled(['city', 'field'])) {
            $field = FootballField::query()
                ->whereKey($request->integer('field'))
                ->where('status', FieldStatus::Active)
                ->where(function ($cityQuery) use ($request) {
                    $cityQuery->where('city_id', $request->integer('city'))
                        ->orWhere(function ($organizationCityQuery) use ($request) {
                            $organizationCityQuery->whereNull('city_id')
                                ->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('city_id', $request->integer('city')));
                        });
                })
                ->with(['city:id,name', 'organization:id,city_id,name,phone,number_of_fields,currency,timezone', 'organization.city:id,name'])
                ->first();
            if ($field) {
                $business = $field->organization->load('city:id,name');
                $pitchAvailability = [[
                    'field' => $field,
                    'slots' => $availability->slots($field, $request->input('date', now()->toDateString())),
                ]];
            }
        }

        return Inertia::render('Public/Availability', [
            'cities' => City::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'businesses' => $businesses,
            'selectedBusiness' => $business,
            'pitchAvailability' => $pitchAvailability,
            'filters' => [
                'city' => $request->integer('city') ?: null,
                'business' => $request->integer('business') ?: null,
                'date' => $request->input('date', now()->toDateString()),
            ],
        ]);
    }
}
