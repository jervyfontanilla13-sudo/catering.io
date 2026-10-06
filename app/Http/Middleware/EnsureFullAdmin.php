<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFullAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('admin_role') !== 'full') {
            abort(403, 'This section is only available to a Primary Admin.');
        }

        return $next($request);
    }
}
