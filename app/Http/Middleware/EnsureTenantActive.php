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

        $isAuthenticated = Auth::guard('tenant')->check();

        if ($tenant->getAttribute('status') !== 'active') {
            if ($isAuthenticated) {
                Auth::guard('tenant')->logout();
            }

            $request->session()->invalidate();
            $request->session()->regenerateToken();

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

        $sessionVersion = $request->session()->get('tenant_session_version');
        $currentVersion = (int) $tenant->getAttribute('session_version');

        if ($isAuthenticated && (
            $sessionVersion === null
            || (int) $sessionVersion !== $currentVersion
        )) {
            Auth::guard('tenant')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->with(
                'status',
                'Your session has expired. Please sign in again.'
            );
        }

        return $next($request);
    }
}
