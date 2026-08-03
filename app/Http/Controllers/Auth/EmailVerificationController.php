<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationController extends Controller
{
    public function notice(): Response|RedirectResponse
    {
        return request()->user()->hasVerifiedEmail()
            ? redirect()->route('dashboard')
            : Inertia::render('Auth/VerifyEmail', [
                'email' => request()->user()->email,
                'canOpenDashboard' => request()->user()->organization?->status?->value === 'approved',
            ]);
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('dashboard');
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('success', __('messages.verification_sent'));
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($request->user()->id),
            ],
        ]);

        $request->user()->forceFill([
            'email' => $validated['email'],
            'email_verified_at' => null,
        ])->save();

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', __('messages.email_updated_verification_sent'));
    }
}
