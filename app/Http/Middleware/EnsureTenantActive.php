<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            abort(404);
        }

        $tenant = tenant();

        if ($tenant->getAttribute('status') === 'active') {
            return $next($request);
        }

        // Revoke any authenticated tenant session.
        if (Auth::guard('tenant')->check()) {
            Auth::guard('tenant')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Allow the login page to display the suspension notice.
        if ($request->isMethod('GET') && $request->is('login')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'This company account is currently suspended.',
            ], 403);
        }

        return redirect('/login')->with(
            'company_suspended',
            'Your company account is currently suspended. Please contact the platform administrator.'
        );
    }
}
