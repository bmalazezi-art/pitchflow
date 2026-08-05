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
            'owner_phone' => ['required', 'string', 'max:32'],
            'business_phone' => ['required', 'string', 'max:32'],
            'city_id' => ['required', 'exists:cities,id'],
            'business_address' => ['required', 'string', 'max:255'],
            'number_of_fields' => ['required', 'integer', 'min:1', 'max:100'],
            'starting_price_per_hour' => ['required', 'numeric', 'min:0', 'max:100000'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i', 'different:opening_time'],
            'amenities' => ['array'],
            'amenities.*' => ['string', 'in:parking,cafe,showers,indoor,outdoor,lighting'],
            'preferred_language' => ['required', 'in:en,sq'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => app()->getLocale() === 'sq'
                ? 'Fjalëkalimet nuk përputhen.'
                : 'Passwords do not match.',
        ];
    }
}
