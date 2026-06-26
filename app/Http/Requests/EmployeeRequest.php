<?php

namespace App\Http\Requests;

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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($employeeId)],
            'phone' => ['nullable', 'string', 'max:32'],
            'preferred_language' => ['required', 'in:en,sq'],
            'field_ids' => ['required', 'array'],
            'field_ids.*' => ['integer'],
        ];
    }
}
