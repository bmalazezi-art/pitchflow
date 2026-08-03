<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function __construct(private readonly PhoneNormalizer $phones) {}

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'max:255']]);
        $identifier = trim($validated['email']);
        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? User::query()->where('email', $identifier)->first()
            : User::query()
                ->where('phone_normalized', $this->phones->normalize($identifier))
                ->where('role', 'employee')
                ->first();

        if ($user && blank($user->email)) {
            return back()->with('success', __('messages.employee_password_reset_owner_help'));
        }

        if ($user?->email) {
            $token = Password::broker()->createToken($user);
            $resetUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);

            if ($this->mailSendingDisabled()) {
                Log::info('PitchFlow password reset link', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'url' => $resetUrl,
                ]);

                return back()
                    ->with('success', __('messages.reset_link_sent'))
                    ->with('reset_notice', __('messages.employee_invitation_email_disabled_local'))
                    ->with('reset_url', $resetUrl);
            }

            $user->sendPasswordResetNotification($token);
        }

        return back()->with('success', __('messages.reset_link_sent'));
    }

    private function mailSendingDisabled(): bool
    {
        return app()->environment(['local', 'development', 'testing'])
            || in_array(config('mail.default'), ['log', 'array'], true);
    }
}
