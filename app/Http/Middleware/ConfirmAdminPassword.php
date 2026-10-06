<?php

namespace App\Http\Middleware;

use App\Services\AdminPasswordVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfirmAdminPassword
{
    public function __construct(private AdminPasswordVerifier $passwordVerifier)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $password = $request->input('current_admin_password');

        if (! is_string($password) || ! $this->passwordVerifier->verify($request, $password)) {
            $message = is_string($password) && $password !== ''
                ? 'The password confirmation is incorrect.'
                : 'Confirm your administrator password to continue.';

            return back()
                ->withErrors(['current_admin_password' => $message])
                ->withInput($request->except('current_admin_password'));
        }

        return $next($request);
    }
}