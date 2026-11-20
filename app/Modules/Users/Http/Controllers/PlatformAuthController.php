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

class PlatformAuthController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('platform')->check()) {
            return redirect()->route('platform.dashboard');
        }

        return Inertia::render('Platform/Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(PortalLoginRequest $request): RedirectResponse
    {
        $request->authenticate('platform');

        return redirect()->intended(route('platform.dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('platform')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
