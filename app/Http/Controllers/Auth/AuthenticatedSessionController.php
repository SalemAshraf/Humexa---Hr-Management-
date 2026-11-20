<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CentralTenantLoginRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Route;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the central company login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Authenticate a tenant account from the central login page and hand
     * the authenticated session off to that tenant's own subdomain.
     */
    public function store(CentralTenantLoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $throttleKey = $request->throttleKey();

        $user = User::query()
            ->where('email', strtolower(trim($validated['email'])))
            ->where('account_type', User::ACCOUNT_TYPE_TENANT)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $tenant = $user->tenant;

        if (! $tenant instanceof Tenant) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (
            $tenant->getAttribute('status') !== 'active'
            || $tenant->getAttribute('suspended_at') !== null
        ) {
            RateLimiter::clear($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Your company workspace is currently suspended. Please contact your platform administrator.',
            ]);
        }

        $baseDomain = strtolower(trim((string) config('humexa.tenant_domain')));
        $domain = strtolower(trim((string) $tenant->domains()->value('domain')));

        // Only redirect to a registered tenant subdomain under our configured
        // base domain. Never trust a user-supplied redirect destination.
        if (
            $baseDomain === ''
            || $domain === ''
            || ! str_ends_with($domain, '.'.$baseDomain)
        ) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('tenant')->login($user, $request->boolean('remember'));

        $request->session()->regenerate();
        $request->session()->put(
            'tenant_session_version',
            (int) $tenant->getAttribute('session_version')
        );

        $scheme = $request->isSecure() ? 'https' : 'http';

        return redirect()->away($scheme.'://'.$domain.'/dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
