<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return Inertia::render('Admin/Cities', [
            'cities' => City::query()->withCount(['organizations', 'footballFields'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ActivityLogger $activity): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2'],
        ]);
        $city = City::create([...$data, 'is_active' => true]);
        $activity->log('city_created', $city);

        return back()->with('success', __('messages.city_created'));
    }

    public function update(Request $request, City $city, ActivityLogger $activity): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $city->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2'],
            'is_active' => ['required', 'boolean'],
        ]));
        $activity->log('city_updated', $city);

        return back()->with('success', __('messages.city_updated'));
    }
}
