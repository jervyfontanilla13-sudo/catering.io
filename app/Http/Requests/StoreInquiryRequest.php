<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'regex:/^(?:\+63\d{10}|09\d{9})$/'],
            'email' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:2000'],
            'website' => ['prohibited'],
            'form_started' => ['required', 'integer'],
            'g-recaptcha-response' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Please provide your full name.',
            'contact_number.required' => 'Please provide a contact number.',
            'contact_number.regex' => 'Enter +63 followed by 10 digits or 09 followed by 9 digits, with no spaces.',
            'email.required' => 'Please provide your email address.',
            'message.required' => 'Please share your inquiry details.',
            'g-recaptcha-response.required' => 'Please verify that you are not a robot.',
        ];
    }
}
