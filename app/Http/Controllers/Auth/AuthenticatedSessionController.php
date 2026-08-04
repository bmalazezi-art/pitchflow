<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly PhoneNormalizer $phones) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $credentials = $request->validated();
        $login = trim((string) $credentials['email']);
        $password = (string) $credentials['password'];

        if ($login === '' || $password === '') {
            RateLimiter::hit($this->throttleKey($request), 60);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $user = $this->userForLogin($login);

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($this->throttleKey($request), 60);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey($request));
        Auth::login($user, $request->boolean('remember'));

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

    private function ensureIsNotRateLimited(LoginRequest $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), $this->maxLoginAttempts())) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => __('messages.too_many_login_attempts'),
        ]);
    }

    private function throttleKey(LoginRequest $request): string
    {
        return Str::lower(trim((string) $request->input('email'))).'|'.$request->ip();
    }

    private function maxLoginAttempts(): int
    {
        return app()->environment('local') ? 10 : 5;
    }

    private function userForLogin(string $login): ?User
    {
        $login = trim($login);

        if ($login === '') {
            return null;
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return User::query()->where('email', $login)->first();
        }

        $matches = User::query()
            ->whereIn('role', ['employee', 'owner'])
            ->where('phone_normalized', $this->phones->normalize($login))
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function destroy(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $activity->log('logout');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Clear-Site-Data' => '"cache"',
        ]);
    }
}
