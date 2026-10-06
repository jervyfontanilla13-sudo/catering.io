<?php

namespace App\Http\Controllers;

use App\Services\AdminPasswordVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManageWebsiteAuthenticationController extends Controller
{
    private const AUTHENTICATION_TTL_MINUTES = 15;

    public function store(Request $request, AdminPasswordVerifier $passwordVerifier): RedirectResponse
    {
        $password = $request->input('current_admin_password');
        $returnTo = $this->adminReturnPath(
            $request->session()->get('manage_website_return_to')
                ?? $request->input('return_to')
        );

        if ($returnTo !== null) {
            $request->session()->put('manage_website_return_to', $returnTo);
        }

        if (! is_string($password)
            || strlen($password) > 512
            || ! $passwordVerifier->verify($request, $password)) {
            return back()
                ->withErrors([
                    'manage_website_password' => 'The administrator password is incorrect. Manage Website remains locked.',
                ])
                ->withInput($request->except('current_admin_password'));
        }

        $request->session()->put([
            'manage_website_auth_user_id' => $request->session()->get('admin_user_id'),
            'manage_website_auth_source' => $request->session()->get('admin_auth_source'),
            'manage_website_auth_email' => strtolower((string) $request->session()->get('admin_email')),
            'manage_website_auth_expires_at' => now()->addMinutes(self::AUTHENTICATION_TTL_MINUTES)->timestamp,
        ]);

        $destination = $request->session()->pull('manage_website_return_to');

        return redirect()->to($this->adminReturnPath($destination) ?? route('admin.dashboard'));
    }

    private function adminReturnPath(mixed $destination): ?string
    {
        if (! is_string($destination) || $destination === '' || str_starts_with($destination, '//')) {
            return null;
        }

        $parts = parse_url($destination);
        $path = $parts['path'] ?? null;

        if (isset($parts['scheme']) || isset($parts['host'])
            || ! is_string($path)
            || ($path !== '/admin' && ! str_starts_with($path, '/admin/'))) {
            return null;
        }

        return $destination;
    }
}
