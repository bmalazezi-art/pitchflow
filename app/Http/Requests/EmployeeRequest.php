<?php

namespace App\Http\Requests;

use App\Support\EmployeePermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() || $this->user()?->isSuperAdmin();
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')?->id;

        return [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->whereNull('deleted_at')->ignore($employeeId)],
            'phone' => ['required', 'string', 'max:32'],
            'preferred_language' => ['required', 'in:en,sq'],
            'field_ids' => ['required', 'array'],
            'field_ids.*' => ['integer'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::in(EmployeePermissions::all())],
        ];
    }
}
