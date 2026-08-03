<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeInvitationController extends Controller
{
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $employee = $this->employeeForToken($token);

        if (! $employee) {
            return redirect()->route('login')->with('error', __('messages.employee_invitation_invalid'));
        }

        if (Auth::check() && Auth::id() !== $employee->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return Inertia::render('Auth/EmployeeInvite', [
            'token' => $token,
            'employee' => [
                'name' => $employee->name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'organization' => $employee->organization?->name,
            ],
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $employee = $this->employeeForToken($token);

        if (! $employee) {
            return redirect()->route('login')->with('error', __('messages.employee_invitation_invalid'));
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $employee->forceFill([
            'password' => Hash::make($validated['password']),
            'status' => 'active',
            'email_verified_at' => $employee->email_verified_at ?? now(),
            'invitation_accepted_at' => now(),
            'invitation_token_hash' => null,
            'invitation_expires_at' => null,
        ])->save();

        return redirect()->route('login')->with('success', __('messages.employee_invitation_accepted'));
    }

    private function employeeForToken(string $token): ?User
    {
        return User::query()
            ->whereIn('role', [UserRole::Employee, UserRole::Owner])
            ->whereIn('status', ['active', 'invited'])
            ->where('invitation_token_hash', hash('sha256', $token))
            ->where('invitation_expires_at', '>', now())
            ->with('organization:id,name')
            ->first();
    }
}
