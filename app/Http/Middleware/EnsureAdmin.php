<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('is_admin', false)) {
            if ($request->routeIs('admin.dashboard')
                && ! User::query()->where('role', 'full')->exists()) {
                return redirect()->route('admin.setup');
            }

            return redirect()->route('admin.login')->with('error', 'You need admin access to continue.');
        }

        if ($request->session()->get('admin_auth_source') === 'emergency') {
            $configuredEmail = config('admin.super_admin_email');
            $configuredPassword = config('admin.super_admin_password');
            $sessionEmail = $request->session()->get('admin_email');
            if ($request->session()->get('admin_role') === 'full'
                && is_string($configuredEmail) && trim($configuredEmail) !== ''
                && is_string($configuredPassword) && $configuredPassword !== ''
                && is_string($sessionEmail)
                && hash_equals(strtolower(trim($configuredEmail)), strtolower($sessionEmail))) {
                return $next($request);
            }

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('error', 'Your emergency administrator session is no longer valid. Please sign in again.');
        }

        $adminUserId = $request->session()->get('admin_user_id');
        $user = $adminUserId === null ? null : User::find($adminUserId);

        if (! $user || ! in_array($user->role, ['full', 'limited'], true) || $user->is_active !== true
            || (int) $user->session_version !== (int) $request->session()->get('admin_session_version', 0)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with(
                'error',
                $user && $user->is_active !== true
                    ? 'Your administrator account has been disabled. Please contact a Primary Admin.'
                    : 'Your administrator session has expired. Please sign in again.'
            );
        }

        $request->session()->put('admin_name', $user->name);
        $request->session()->put('admin_email', $user->email);
        $request->session()->put('admin_role', $user->role);

        return $next($request);
    }
}
