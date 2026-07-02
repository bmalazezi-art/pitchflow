<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request, ActivityLogger $activity): RedirectResponse
    {
        if (! Auth::attempt($request->safe()->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();
        if ($request->user()->isEmployee() && ! $request->user()->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email' => __('messages.account_disabled')]);
        }
        $request->user()->forceFill(['last_login_at' => now()])->save();
        $activity->log('login');

        $destination = match (true) {
            $request->user()->isSuperAdmin() => route('admin.organizations'),
            $request->user()->isOwner() => route('dashboard'),
            default => route('dashboard'),
        };

        return redirect()->intended($destination);
    }

    public function destroy(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $activity->log('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
