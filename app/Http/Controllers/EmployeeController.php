<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\EmployeeRequest;
use App\Models\FootballField;
use App\Models\User;
use App\Notifications\EmployeeInvitation;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
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
        unset($data['first_name'], $data['last_name'], $data['field_ids']);

        $employee = DB::transaction(function () use ($request, $data, $fieldIds, $name) {
            $employee = User::create([
                ...$data,
                'name' => $name,
                'organization_id' => $request->user()->organization_id,
                'role' => UserRole::Employee,
                'password' => Str::password(40),
                'status' => 'invited',
                'invited_at' => now(),
            ]);
            $employee->assignedFields()->syncWithPivotValues($fieldIds, [
                'organization_id' => $request->user()->organization_id,
            ]);

            return $employee;
        });
        $employee->notify(new EmployeeInvitation(Password::broker()->createToken($employee)));
        $activity->log('employee_created', $employee);

        return back()->with('success', __('messages.employee_created'));
    }

    public function update(EmployeeRequest $request, User $employee, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('update', $employee);
        $data = $request->validated();
        $fieldIds = $this->validFieldIds($request, $data['field_ids']);
        $data['name'] = trim($data['first_name'].' '.$data['last_name']);
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

    private function validFieldIds(EmployeeRequest $request, array $fieldIds): array
    {
        $valid = FootballField::query()->forOrganization($request->user()->organization_id)
            ->whereIn('id', $fieldIds)->pluck('id')->all();
        if (count($valid) !== count(array_unique($fieldIds))) {
            abort(422, __('messages.invalid_field_assignment'));
        }

        return $valid;
    }
}
