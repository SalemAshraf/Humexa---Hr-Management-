<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts created with a password someone else chose must replace it before
 * reaching anything else in the company workspace.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('tenant');

        if ($user instanceof User && $user->must_change_password && ! $request->routeIs('tenant.password.*', 'tenant.logout')) {
            if ($request->expectsJson()) {
                abort(403, 'You must change your password first.');
            }

            return redirect('/password/change');
        }

        return $next($request);
    }
}
