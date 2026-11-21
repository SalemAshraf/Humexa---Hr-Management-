<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: tenant.can:{read permission},{write permission}
 * GET/HEAD need the first one, every other method needs the second.
 */
class EnsureTenantPermission
{
    public function handle(Request $request, Closure $next, string $read, ?string $write = null): Response
    {
        $user = $request->user('tenant');

        abort_unless($user instanceof User, 403);

        $needed = $request->isMethodSafe() ? $read : ($write ?? $read);

        abort_unless($user->can($needed), 403);

        return $next($request);
    }
}
