<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9 ]*[0-9][0-9 ]*$/'],
            'football_field_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'status' => ['sometimes', 'in:pending,confirmed,completed,no_show'],
            'payment_status' => ['required', 'in:unpaid,partial,paid'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'is_walk_in' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_phone.regex' => 'Numri i telefonit nuk është valid.',
        ];
    }
}
