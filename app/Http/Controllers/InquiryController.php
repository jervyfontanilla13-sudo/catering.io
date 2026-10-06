<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInquiryRequest;
use App\Models\Inquiry;
use ReCaptcha\ReCaptcha;

class InquiryController extends Controller
{
    public function store(StoreInquiryRequest $request)
    {
        if (now()->timestamp - (int) $request->input('form_started') < 3) {
            return back()->withInput()->withErrors(['full_name' => 'Unable to submit this request. Please try again.']);
        }

        // Verify reCAPTCHA
        $recaptcha = new ReCaptcha(config('services.recaptcha.secret_key'));
        $resp = $recaptcha->verify($request->input('g-recaptcha-response'), $_SERVER['REMOTE_ADDR'] ?? '');
        
        if (!$resp->isSuccess()) {
            return back()->withInput()->withErrors(['g-recaptcha-response' => 'Please verify that you are not a robot.']);
        }

        Inquiry::create([
            'full_name' => $request->input('full_name'),
            'contact_number' => $request->input('contact_number'),
            'email' => $request->input('email'),
            'subject' => $request->input('subject'),
            'category' => $request->input('category'),
            'message' => $request->input('message'),
        ]);

        return redirect()->back()->with('success', 'Your inquiry has been sent.');
    }
}
