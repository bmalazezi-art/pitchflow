<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationSettingsRequest;
use App\Models\City;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function edit(): Response
    {
        abort_unless(request()->user()->isOwner(), 403);

        return Inertia::render('Settings/Organization', [
            'organization' => request()->user()->organization,
            'cities' => City::query()->forSelector()->inKosovoSelectorOrder()->get(['id', 'name']),
        ]);
    }

    public function update(OrganizationSettingsRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $organization = $request->user()->organization;
        $organization->update($request->validated());
        $activity->log('settings_updated', $organization);

        return back()->with('success', __('messages.settings_updated'));
    }
}
