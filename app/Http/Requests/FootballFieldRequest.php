<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FootballFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() || $this->user()?->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,maintenance,closed'],
            'price_per_hour' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i'],
            'operating_hours' => ['sometimes', 'array'],
            'operating_hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'operating_hours.*.opening_time' => ['required', 'date_format:H:i'],
            'operating_hours.*.closing_time' => ['required', 'date_format:H:i'],
            'operating_hours.*.is_closed' => ['required', 'boolean'],
        ];
    }
}
