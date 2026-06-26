<?php

namespace App\Http\Controllers;

use App\Enums\FieldStatus;
use App\Enums\OrganizationStatus;
use App\Models\City;
use App\Models\FootballField;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicAvailabilityController extends Controller
{
    public function __invoke(Request $request, AvailabilityService $availability): Response
    {
        $field = null;
        $slots = [];

        if ($request->filled('field')) {
            $field = FootballField::query()
                ->whereKey($request->integer('field'))
                ->where('status', FieldStatus::Active)
                ->whereHas('organization', fn ($query) => $query->where('status', OrganizationStatus::Approved))
                ->with('organization:id,name,timezone')
                ->first();
            if ($field) {
                $slots = $availability->slots($field, $request->input('date', now()->toDateString()));
            }
        }

        return Inertia::render('Public/Availability', [
            'cities' => City::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'fields' => FootballField::query()
                ->where('status', FieldStatus::Active)
                ->whereHas('organization', fn ($query) => $query->where('status', OrganizationStatus::Approved))
                ->when($request->filled('city'), fn ($query) => $query->where(function ($cityQuery) use ($request) {
                    $cityQuery->where('city_id', $request->integer('city'))
                        ->orWhere(function ($organizationCityQuery) use ($request) {
                            $organizationCityQuery->whereNull('city_id')
                                ->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('city_id', $request->integer('city')));
                        });
                }))
                ->with('organization:id,name')
                ->orderBy('name')->get(['id', 'organization_id', 'city_id', 'name', 'address']),
            'selectedField' => $field,
            'slots' => $slots,
            'filters' => [
                'city' => $request->integer('city') ?: null,
                'field' => $request->integer('field') ?: null,
                'date' => $request->input('date', now()->toDateString()),
            ],
        ]);
    }
}
