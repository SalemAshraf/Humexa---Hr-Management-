<?php

declare(strict_types=1);

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My account": the signed-in person edits only their own display name and language.
 * The sign-in email is shown but not editable here (it is the login identity), and the
 * password has its own page. No account id is accepted from the request.
 */
class TenantAccountController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('tenant');

        return Inertia::render('Company/Account/Edit', [
            'account' => ['name' => $user->name, 'email' => $user->email, 'locale' => $user->locale ?: 'en'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user('tenant');

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'locale' => ['required', 'in:en,ar'],
        ]);

        $user->forceFill(['name' => trim($data['name']), 'locale' => $data['locale']])->save();
        Cookie::queue('locale', $data['locale'], 60 * 24 * 365);

        return back()->with('success', __('Your account was updated.'));
    }
}
