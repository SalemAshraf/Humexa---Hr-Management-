<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('tenant');

        if (! tenancy()->initialized) {
            abort(404);
        }

        $tenantId = (string) tenant()->getTenantKey();

        abort_unless(
            $user instanceof User
            && $user->account_type === User::ACCOUNT_TYPE_TENANT
            && $user->tenant_id !== null
            && hash_equals((string) $user->tenant_id, $tenantId),
            403
        );

        if ($user->disabled_at !== null) {
            \Illuminate\Support\Facades\Auth::guard('tenant')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->expectsJson()) {
                abort(403, 'This account is deactivated.');
            }

            return redirect('/login')->with('status', 'This account is deactivated. Ask your company owner.');
        }

        return $next($request);
    }
}

