<?php

namespace App\Http\Requests;

use App\Enums\ReliabilityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'preferred_field_id' => ['nullable', 'integer'],
            'reliability_status' => ['nullable', Rule::enum(ReliabilityStatus::class)],
        ];
    }
}
