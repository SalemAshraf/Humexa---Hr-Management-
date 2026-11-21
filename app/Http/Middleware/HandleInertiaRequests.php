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

            'locale' => fn () => app()->getLocale(),
            'dir' => fn () => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'translations' => function () {
                return app()->getLocale() === 'en' ? (object) [] : (object) app('translator')->getLoader()->load(app()->getLocale(), '*', '*');
            },

            'is_owner' => function () use ($request) {
                $user = $request->user('tenant');

                return $user instanceof \App\Models\User && tenancy()->initialized && $user->hasRole(\App\Modules\Users\Support\TenantRoleCatalog::OWNER);
            },

            'attention_count' => function () use ($request) {
                $user = $request->user('tenant');

                if (! $user instanceof \App\Models\User || ! tenancy()->initialized) {
                    return 0;
                }

                return app(\App\Modules\Notifications\Services\AttentionService::class)->total($user, (string) tenant()->getTenantKey());
            },

            'flash' => [
                'status' => fn () => $this->translated($request->session()->get('status')),
                'success' => fn () => $this->translated($request->session()->get('success')),
                'registered_domain' => fn () => $request->session()->get('registered_domain'),
            ],
        ];
    }

    /** Message text written in English in the code; translated when the active language has it. */
    private function translated(mixed $message): mixed
    {
        return is_string($message) ? __($message) : $message;
    }

    public function resolveValidationErrors(Request $request)
    {
        $errors = parent::resolveValidationErrors($request);
        $map = fn ($v) => is_string($v) ? __($v) : $v;

        return (object) collect((array) $errors)->map(fn ($v) => is_object($v) || is_array($v) ? (object) collect((array) $v)->map($map)->all() : $map($v))->all();
    }
}
