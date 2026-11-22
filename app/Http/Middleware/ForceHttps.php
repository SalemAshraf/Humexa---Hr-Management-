<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional HTTP to HTTPS redirect for the web routes, enabled with
 * HUMEXA_FORCE_HTTPS=true. It keeps the same host, path and query string.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.force_https') || $request->isSecure()) {
            return $next($request);
        }

        $url = 'https://'.$request->getHttpHost().$request->getRequestUri();

        // 308 keeps the method and body for non-GET requests.
        return redirect()->away(
            $url,
            in_array($request->getMethod(), ['GET', 'HEAD'], true) ? 301 : 308
        );
    }
}
