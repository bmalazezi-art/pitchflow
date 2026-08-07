<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeProfileRequest;
use App\Services\ActivityLogger;
use App\Services\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private readonly PhoneNormalizer $phones) {}

    public function edit(): Response
    {
        $user = request()->user();

        return Inertia::render('Employee/Profile', [
            'employee' => $user->load('assignedFields:id,name,status'),
        ]);
    }

    public function update(EmployeeProfileRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $data = $request->validated();
        $data['phone_normalized'] = filled($data['phone'] ?? null) ? $this->phones->normalize($data['phone']) : null;

        $request->user()->update($data);
        $activity->log('profile_updated', $request->user());

        return back()->with('success', __('messages.settings_updated'));
    }
}
