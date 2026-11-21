<?php

declare(strict_types=1);

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Http\Requests\PortalLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TenantAuthController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('tenant')->check()) {
            return redirect('/dashboard');
        }

        return Inertia::render('Auth/Login', [
    'canResetPassword' => false,
    'status' => session('status'),
    'companySuspended' => session('company_suspended'),
    'loginUrl' => url('/login'),
    'showRegistrationLink' => true,
]);
    }

    
public function store(PortalLoginRequest $request): RedirectResponse
{
    $request->authenticate('tenant');

    $request->session()->put(
        'tenant_session_version',
        (int) tenant()->getAttribute('session_version')
    );

    return redirect()->intended('/dashboard');
}


    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('tenant')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}


