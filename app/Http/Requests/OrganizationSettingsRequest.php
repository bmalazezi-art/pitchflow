<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrganizationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'city_id' => ['required', 'exists:cities,id'],
            'address' => ['required', 'string', 'max:255'],
            'timezone' => ['required', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== 'Europe/Pristina' && ! in_array($value, timezone_identifiers_list(), true)) {
                    $fail(__('validation.timezone', ['attribute' => $attribute]));
                }
            }],
            'currency' => ['required', 'string', 'size:3'],
            'locale' => ['required', 'in:en,sq'],
            'cancellation_window_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
        ];
    }
}
