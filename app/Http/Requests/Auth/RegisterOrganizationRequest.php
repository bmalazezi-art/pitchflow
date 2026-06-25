<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'business_name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:32'],
            'city_id' => ['required', 'exists:cities,id'],
            'business_address' => ['required', 'string', 'max:255'],
            'number_of_fields' => ['required', 'integer', 'min:1', 'max:100'],
            'preferred_language' => ['required', 'in:en,sq'],
        ];
    }
}
