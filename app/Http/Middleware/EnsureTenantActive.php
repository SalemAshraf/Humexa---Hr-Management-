<?php

declare(strict_types=1);

namespace App\\Http\\Middleware;

use Closure;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Auth;
use Symfony\\Component\\HttpFoundation\\Response;

class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            abort(404);
        }

        $tenant = tenant();
        $hasSession = $request->hasSession();
        $isAuthenticated = $hasSession && Auth::guard('tenant')->check();

        // Block all access when the company is suspended.
        if ($tenant->getAttribute('status') !== 'active') {
            if ($isAuthenticated) {
                Auth::guard('tenant')->logout();
            }

            if ($hasSession) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $message = 'Your company account is currently suspended. Please contact the platform administrator.';

            // Allow the login page to display the suspension notice.
            if ($hasSession && $request->isMethod('GET') && $request->is('login')) {
                $request->session()->flash('company_suspended', $message);

                return $next($request);
            }

            // API routes are stateless and must never depend on a session.
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 403);
            }

            return redirect('/login')->with('company_suspended', $message);
        }

        // Session-version checks apply only to stateful web requests.
        if ($hasSession) {
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
        }

        return $next($request);
    }
}
