<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterOrganizationRequest;
use App\Models\City;
use App\Services\BusinessRegistrationNotifier;
use App\Services\OrganizationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'cities' => City::query()->forSelector()->inKosovoSelectorOrder()->get(['id', 'name']),
        ]);
    }

    public function store(
        RegisterOrganizationRequest $request,
        OrganizationService $organizations,
        BusinessRegistrationNotifier $notifier,
    ): RedirectResponse {
        $user = $organizations->register($request->validated());
        event(new Registered($user));
        $notifier->applicationReceived($user);
        Auth::login($user);

        return redirect()->route('approval.pending');
    }
}
