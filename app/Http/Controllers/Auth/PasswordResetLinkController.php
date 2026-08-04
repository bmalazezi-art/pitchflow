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
use Throwable;

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
            if ($this->mailSendingDisabled()) {
                if (! $this->localResetLinkFallbackEnabled()) {
                    Log::warning('PitchFlow password reset unavailable because mail is disabled outside local mode.', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'mailer' => config('mail.default'),
                        'environment' => app()->environment(),
                    ]);

                    return back()->with('success', __('messages.password_reset_temporarily_unavailable'));
                }

                $token = Password::broker()->createToken($user);
                $resetUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);

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

            try {
                $token = Password::broker()->createToken($user);
                $user->sendPasswordResetNotification($token);
            } catch (Throwable $exception) {
                Log::error('PitchFlow password reset email failed.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'mailer' => config('mail.default'),
                    'message' => $exception->getMessage(),
                ]);

                return back()->with('success', __('messages.password_reset_temporarily_unavailable'));
            }
        }

        return back()->with('success', __('messages.reset_link_sent'));
    }

    private function mailSendingDisabled(): bool
    {
        return in_array(config('mail.default'), ['log', 'array'], true);
    }

    private function localResetLinkFallbackEnabled(): bool
    {
        return config('app.env') === 'local';
    }
}
