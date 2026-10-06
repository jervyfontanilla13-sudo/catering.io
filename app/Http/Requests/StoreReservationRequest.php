<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:255', 'regex:/^[\pL\pN\s\.\'\-]+$/u'],
            'contact_number' => ['required', 'string', 'regex:/^(?:\+63\d{10}|09\d{9})$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'regex:/^[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+$/'],
            'address' => ['required', 'string', 'min:8', 'max:500', function ($attribute, $value, $fail) {
                $normalized = trim((string) $value);
                $lower = strtolower($normalized);
                $blocked = ['n/a', 'na', 'not applicable', 'sample address', 'test address', 'address', 'example', 'dummy', 'tbd'];

                if ($normalized === '' || in_array($lower, $blocked, true) || !preg_match('/[A-Za-z]/', $normalized) || preg_match('/^(?:\d[\s#.,\/\-]*)+$/', $normalized)) {
                    $fail('Please provide a complete and valid address.');
                }
            }],
            'event_type' => ['required', 'string', 'max:100'],
            'event_date' => ['required', 'date', 'after_or_equal:' . now()->addDays(2)->toDateString(), function ($attribute, $value, $fail) {
                $minDate = now()->addDays(2)->toDateString();
                if ($value < $minDate) {
                    $fail('Reservations must be scheduled at least 2 days in advance.');
                }
            }],
            'event_time' => ['required', 'string', 'max:20'],
            'venue' => ['required', 'string', 'min:3', 'max:255', function ($attribute, $value, $fail) {
                $normalized = strtolower(trim((string) $value));
                $blocked = ['n/a', 'na', 'not applicable', 'sample venue', 'test venue', 'venue', 'tbd', 'to be determined', 'dummy'];

                if ($normalized === '' || in_array($normalized, $blocked, true) || !preg_match('/[A-Za-z]/', $normalized)) {
                    $fail('Please provide a valid venue name.');
                }
            }],
            'guest_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'package_id' => ['required', 'exists:packages,id'],
            'additional_services' => ['nullable', 'string', 'max:1000'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'additional_notes' => ['nullable', 'string', 'max:1000'],
            'submission_key' => ['nullable', 'string', 'regex:/\A[A-Za-z0-9]{64}\z/'],
            'website' => ['prohibited'],
            'form_started' => ['required', 'integer'],
            'g-recaptcha-response' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Please provide your full name.',
            'full_name.regex' => 'Please enter a valid full name without special characters.',
            'contact_number.required' => 'Please provide a contact number.',
            'contact_number.regex' => 'Enter +63 followed by 10 digits or 09 followed by 9 digits, with no spaces.',
            'email.required' => 'Please provide your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.regex' => 'Please enter a valid email address.',
            'address.required' => 'Please provide your complete address.',
            'address.min' => 'Please provide a complete and valid address.',
            'address.regex' => 'Please provide a complete and valid address.',
            'venue.required' => 'Please provide the event venue.',
            'venue.min' => 'Please provide a valid venue name.',
            'venue.regex' => 'Please provide a valid venue name.',
            'event_date.after_or_equal' => 'Event date must be today or later.',
            'guest_count.max' => 'Guest count cannot exceed 1000.',
            'package_id.required' => 'Please select a catering package.',
            'package_id.exists' => 'The selected package is no longer available. Please choose another package.',
            'g-recaptcha-response.required' => 'Please verify that you are not a robot.',
        ];
    }
}
