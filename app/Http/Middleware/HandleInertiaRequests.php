<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user(),
            ],

            // What the signed-in company account may open, so screens can hide what would be refused.
            'can' => function () use ($request) {
                $user = $request->user('tenant');

                if (! $user instanceof \App\Models\User || ! tenancy()->initialized) {
                    return (object) [];
                }

                return collect(\App\Modules\Users\Support\TenantRoleCatalog::PERMISSIONS)
                    ->mapWithKeys(fn ($permission) => [$permission => $user->can($permission)]);
            },

            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
                'registered_domain' => fn () => $request->session()->get('registered_domain'),
            ],
        ];
    }
}
