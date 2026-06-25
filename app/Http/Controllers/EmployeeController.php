<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\EmployeeRequest;
use App\Models\FootballField;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        $organizationId = request()->user()->organization_id;

        return Inertia::render('Employees/Index', [
            'employees' => User::query()->where('organization_id', $organizationId)
                ->where('role', UserRole::Employee)->with('assignedFields:id,name')->paginate(15),
            'fields' => FootballField::query()->forOrganization($organizationId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(EmployeeRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $request->validated();
        $fieldIds = $this->validFieldIds($request, $data['field_ids']);

        $employee = DB::transaction(function () use ($request, $data, $fieldIds) {
            $employee = User::create([
                ...$data,
                'organization_id' => $request->user()->organization_id,
                'role' => UserRole::Employee,
            ]);
            $employee->assignedFields()->sync($fieldIds);

            return $employee;
        });
        $activity->log('employee_created', $employee);

        return back()->with('success', __('messages.employee_created'));
    }

    public function update(EmployeeRequest $request, User $employee, ActivityLogger $activity): RedirectResponse
    {
        $this->authorize('update', $employee);
        $data = $request->validated();
        $fieldIds = $this->validFieldIds($request, $data['field_ids']);
        unset($data['field_ids'], $data['password_confirmation']);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        DB::transaction(function () use ($employee, $data, $fieldIds) {
            $employee->update($data);
            $employee->assignedFields()->sync($fieldIds);
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

    private function validFieldIds(EmployeeRequest $request, array $fieldIds): array
    {
        $valid = FootballField::query()->forOrganization($request->user()->organization_id)
            ->whereIn('id', $fieldIds)->pluck('id')->all();
        abort_unless(count($valid) === count(array_unique($fieldIds)), 422, __('messages.invalid_field_assignment'));

        return $valid;
    }
}
