<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            if (tenancy()->initialized) {
                return '/login';
            }

            if ($request->is('platform') || $request->is('platform/*')) {
                return '/platform/login';
            }

            return '/login';
        });
        $middleware->alias([
        'tenant.user' => \App\Http\Middleware\EnsureTenantUser::class,
        'platform.user' => \App\Http\Middleware\EnsurePlatformUser::class,
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(
            function (TenantCouldNotBeIdentifiedOnDomainException $exception, Request $request) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Not Found',
                    ], 404);
                }

                return response('Not Found', 404);
            }
        );
    })->create();
