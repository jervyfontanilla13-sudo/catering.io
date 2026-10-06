<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManageWebsiteAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $expiresAt = $request->session()->get('manage_website_auth_expires_at');
        $authenticated = $request->session()->get('manage_website_auth_user_id')
                === $request->session()->get('admin_user_id')
            && $request->session()->get('manage_website_auth_source')
                === $request->session()->get('admin_auth_source')
            && $request->session()->get('manage_website_auth_email')
                === strtolower((string) $request->session()->get('admin_email'))
            && is_numeric($expiresAt)
            && (int) $expiresAt > now()->timestamp;

        if ($request->session()->get('admin_role') === 'full' && $authenticated) {
            return $next($request);
        }

        $request->session()->forget([
            'manage_website_auth_user_id',
            'manage_website_auth_source',
            'manage_website_auth_email',
            'manage_website_auth_expires_at',
        ]);
        $request->session()->put('manage_website_return_to', $request->getRequestUri());

        return redirect()->route('admin.dashboard')
            ->with('manage_website_auth_required', true);
    }
}
