<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\EmployeeRequest;
use App\Models\FootballField;
use App\Models\User;
use App\Notifications\EmployeeInvitation;
use App\Services\ActivityLogger;
use App\Services\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(private readonly PhoneNormalizer $phones) {}

    public function index(): Response
    {
        abort_unless(request()->user()->isOwner() || request()->user()->isSuperAdmin(), 403);
        $organizationId = request()->user()->organization_id;
        $employeeQuery = User::query()->where('organization_id', $organizationId)
            ->where('role', UserRole::Employee);

        return Inertia::render('Employees/Index', [
            'employees' => (clone $employeeQuery)->with('assignedFields:id,name')->paginate(15),
            'stats' => [
                'total' => (clone $employeeQuery)->count(),
                'active' => (clone $employeeQuery)->where('status', 'active')->count(),
                'invited' => (clone $employeeQuery)->where('status', 'invited')->count(),
                'disabled' => (clone $employeeQuery)->where('status', 'disabled')->count(),
            ],
            'fields' => FootballField::query()->forOrganization($organizationId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(EmployeeRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $request->validated();
        $fieldIds = $this->validFieldIds($request, $data['field_ids']);
        $name = trim($data['first_name'].' '.$data['last_name']);
        $data['email'] = blank($data['email'] ?? null) ? null : $data['email'];
        $data['phone_normalized'] = $this->phones->normalize($data['phone']);
        $this->releaseTrashedEmployeeIdentityConflicts($request, $data['email'], $data['phone_normalized']);
        $this->ensureUniqueEmployeePhone($request, $data['phone_normalized']);
        unset($data['first_name'], $data['last_name'], $data['field_ids']);

        [$employee, $token] = DB::transaction(function () use ($request, $data, $fieldIds, $name) {
            $token = Str::random(64);
            $employee = User::create([
                ...$data,
                'name' => $name,
                'organization_id' => $request->user()->organization_id,
                'role' => UserRole::Employee,
                'password' => null,
                'status' => 'invited',
                'invited_at' => now(),
                'invitation_token_hash' => hash('sha256', $token),
                'invitation_token' => $token,
                'invitation_expires_at' => now()->addDays(7),
            ]);
            $employee->assignedFields()->syncWithPivotValues($fieldIds, [
                'organization_id' => $request->user()->organization_id,
            ]);

            return [$employee, $token];
        });
        $this->sendInvitationIfConfigured($employee, $token);
        $activity->log('employee_created', $employee);

        return $this->invitationResponse($employee, $token);
    }

    public function update(EmployeeRequest $request, User $employee, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('update', $employee);
        $data = $request->validated();
        $fieldIds = $this->validFieldIds($request, $data['field_ids']);
        $data['name'] = trim($data['first_name'].' '.$data['last_name']);
        $data['email'] = blank($data['email'] ?? null) ? null : $data['email'];
        $data['phone_normalized'] = $this->phones->normalize($data['phone']);
        $this->releaseTrashedEmployeeIdentityConflicts($request, $data['email'], $data['phone_normalized']);
        $this->ensureUniqueEmployeePhone($request, $data['phone_normalized'], $employee->id);
        unset($data['first_name'], $data['last_name'], $data['field_ids']);

        DB::transaction(function () use ($request, $employee, $data, $fieldIds) {
            $employee->update($data);
            $employee->assignedFields()->syncWithPivotValues($fieldIds, [
                'organization_id' => $request->user()->organization_id,
            ]);
        });
        $activity->log('employee_updated', $employee);

        return back()->with('success', __('messages.employee_updated'));
    }

    public function destroy(User $employee, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('delete', $employee);
        $this->releaseEmployeeIdentity($employee);
        $employee->delete();
        $activity->log('employee_deleted', $employee);

        return back()->with('success', __('messages.employee_deleted'));
    }

    public function status(User $employee, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('update', $employee);
        $employee->update(['status' => $employee->status === 'disabled' ? 'active' : 'disabled']);
        $activity->log($employee->status === 'disabled' ? 'employee_disabled' : 'employee_enabled', $employee);

        return back()->with('success', __('messages.employee_updated'));
    }

    public function resendInvitation(User $employee, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('update', $employee);
        abort_unless($employee->status === 'invited', 422, __('messages.employee_invitation_not_available'));

        $existingToken = $this->validInvitationToken($employee);
        $token = $existingToken ?? Str::random(64);

        if (! $existingToken) {
            $employee->forceFill([
                'invited_at' => now(),
                'invitation_token_hash' => hash('sha256', $token),
                'invitation_token' => $token,
                'invitation_expires_at' => now()->addDays(7),
            ])->save();
        }

        $this->sendInvitationIfConfigured($employee, $token);
        $activity->log('employee_invitation_resent', $employee);

        return $this->invitationResponse($employee, $token);
    }

    public function createPasswordResetLink(User $employee, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('update', $employee);
        abort_unless(in_array($employee->status, ['active', 'invited'], true), 422, __('messages.employee_password_reset_not_available'));

        $token = Str::random(64);
        $employee->forceFill([
            'invitation_token_hash' => hash('sha256', $token),
            'invitation_token' => $token,
            'invitation_expires_at' => now()->addDays(7),
        ])->save();

        $resetUrl = route('employee.invite.show', ['token' => $token]);

        if ($this->mailSendingDisabled()) {
            logger()->info('PitchFlow employee password reset link', [
                'employee_id' => $employee->id,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'url' => $resetUrl,
            ]);
        }

        $activity->log('employee_password_reset_link_created', $employee);

        $response = back()
            ->with('success', __('messages.employee_password_reset_link_created'))
            ->with('reset_url', $resetUrl)
            ->with('reset_link', $resetUrl);

        return $response;
    }

    private function validFieldIds(EmployeeRequest $request, array $fieldIds): array
    {
        $valid = FootballField::query()->forOrganization($request->user()->organization_id)
            ->whereIn('id', $fieldIds)->pluck('id')->all();
        if (count($valid) !== count(array_unique($fieldIds))) {
            abort(422, __('messages.invalid_field_assignment'));
        }

        return $valid;
    }

    private function sendInvitationIfConfigured(User $employee, string $token): void
    {
        if ($this->shouldShowInviteLink($employee)) {
            logger()->info('PitchFlow employee invitation link', [
                'employee_id' => $employee->id,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'url' => route('employee.invite.show', ['token' => $token]),
            ]);

            return;
        }

        $employee->notify(new EmployeeInvitation($token));
    }

    private function invitationResponse(User $employee, string $token): RedirectResponse
    {
        $inviteUrl = route('employee.invite.show', ['token' => $token]);
        $shouldShowInviteUrl = $this->mailSendingDisabled() || blank($employee->email);
        $response = back()->with('success', __('messages.employee_invitation_created_success'));

        if ($shouldShowInviteUrl) {
            $response->with('invite_url', $inviteUrl);
            $response->with('invite_link', $inviteUrl);
        }

        return $response;
    }

    private function validInvitationToken(User $employee): ?string
    {
        if (blank($employee->invitation_token) || ! $employee->invitation_expires_at?->isFuture()) {
            return null;
        }

        return hash('sha256', $employee->invitation_token) === $employee->invitation_token_hash
            ? $employee->invitation_token
            : null;
    }

    private function shouldShowInviteLink(User $employee): bool
    {
        return $this->mailSendingDisabled() || blank($employee->email);
    }

    private function mailSendingDisabled(): bool
    {
        return app()->environment(['local', 'development', 'testing'])
            || in_array(config('mail.default'), ['log', 'array'], true);
    }

    private function ensureUniqueEmployeePhone(EmployeeRequest $request, string $normalizedPhone, ?int $ignoreId = null): void
    {
        $exists = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', UserRole::Employee)
            ->where('phone_normalized', $normalizedPhone)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['phone' => __('messages.employee_phone_taken')]);
        }
    }

    private function releaseTrashedEmployeeIdentityConflicts(EmployeeRequest $request, ?string $email, string $normalizedPhone): void
    {
        User::onlyTrashed()
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', UserRole::Employee)
            ->where(function ($query) use ($email, $normalizedPhone) {
                $query->where('phone_normalized', $normalizedPhone);

                if (filled($email)) {
                    $query->orWhere('email', $email);
                }
            })
            ->get()
            ->each(fn (User $employee) => $this->releaseEmployeeIdentity($employee));
    }

    private function releaseEmployeeIdentity(User $employee): void
    {
        $employee->forceFill([
            'email' => filled($employee->email) ? "deleted-user-{$employee->id}@pitchflow.local" : null,
            'phone' => null,
            'phone_normalized' => null,
        ])->saveQuietly();
    }
}
