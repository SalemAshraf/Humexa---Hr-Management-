<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Users\Support\TenantRoleCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** My space is for employees. An account holding the Owner role never uses it (owner rule from the product owner). */
class ExcludeOwnerFromSelfService
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('tenant');

        if ($user instanceof User && $user->hasRole(TenantRoleCatalog::OWNER)) {
            abort_unless($request->isMethod('GET'), 403);

            return redirect('/dashboard');
        }

        return $next($request);
    }
}
