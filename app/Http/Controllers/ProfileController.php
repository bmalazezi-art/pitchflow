<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeProfileRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        abort_unless(request()->user()->isEmployee(), 403);

        return Inertia::render('Employee/Profile', [
            'employee' => request()->user()->load('assignedFields:id,name,status'),
        ]);
    }

    public function update(EmployeeProfileRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $request->user()->update($request->validated());
        $activity->log('profile_updated', $request->user());

        return back()->with('success', __('messages.settings_updated'));
    }
}
