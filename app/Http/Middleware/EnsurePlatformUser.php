<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('platform');

        abort_unless(
            ! tenancy()->initialized
            && $user instanceof User
            && $user->account_type === User::ACCOUNT_TYPE_PLATFORM
            && $user->tenant_id === null,
            403
        );

        return $next($request);
    }
}

