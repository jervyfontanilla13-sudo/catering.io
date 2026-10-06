<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'contact_number' => ['required', 'string', 'regex:/^(?:\+63\d{10}|09\d{9})$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'address' => ['required', 'string', 'min:8', 'max:500'],
            'event_type' => ['required', 'string', 'max:100'],
            'event_date' => ['required', 'date'],
            'event_time' => ['required', 'date_format:H:i'],
            'venue' => ['required', 'string', 'min:3', 'max:255'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'package_id' => ['required', 'exists:packages,id'],
            'additional_services' => ['nullable', 'string', 'max:1000'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'additional_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_number.regex' => 'Enter +63 followed by 10 digits or 09 followed by 9 digits, with no spaces.',
        ];
    }
}