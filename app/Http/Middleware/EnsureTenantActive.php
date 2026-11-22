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

        // Owner decisions: after the 7-day trial without a manual activation, or after the
        // paid-until date, the company keeps read/export access but nothing can be added or changed.
        if ($this->trialExpired($tenant) && ! $request->isMethodSafe()
            && ! $request->is('login', 'logout', 'locale', 'password/change', 'account', 'mcp', 'oauth/*', 'ai-connections/*', 'api/v1/auth/*', 'v1/auth/*')) {
            $message = 'Your plan period has ended. Your data is kept and you can still view and export it. Ask the platform administrator to renew your plan to add or change records.';

            if ($request->is('api/*', 'v1/*') || $request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $message], 403);
            }

            return back()->withErrors(['trial' => $message]);
        }

        return $next($request);
    }

    /**
     * True when the company has no running access: a trial that ended without an
     * activation, or a paid-until date that has passed. The last day counts in full,
     * on the company's calendar.
     */
    private function trialExpired($tenant): bool
    {
        $paidUntil = $tenant->getAttribute('subscription_ends_at');
        $limit = $paidUntil ?: $tenant->getAttribute('trial_ends_at');

        if (! $limit) {
            return false;
        }

        $today = \App\Modules\Settings\Support\CompanyClock::today((string) $tenant->getKey());

        return $today > \Illuminate\Support\Carbon::parse($limit)->toDateString();
    }
}
