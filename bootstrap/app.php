<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The language cookie holds only "en" or "ar" and is validated on read.
        $middleware->encryptCookies(except: ['locale']);

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
            'tenant.active' => \App\Http\Middleware\EnsureTenantActive::class,
            'tenant.user' => \App\Http\Middleware\EnsureTenantUser::class,
            'tenant.password' => \App\Http\Middleware\EnsurePasswordChanged::class,
            'tenant.can' => \App\Http\Middleware\EnsureTenantPermission::class,
            'platform.user' => \App\Http\Middleware\EnsurePlatformUser::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
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
