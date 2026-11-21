<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'in:'.implode(',', SetLocale::SUPPORTED)]]);

        Cookie::queue('locale', $data['locale'], 60 * 24 * 365);

        // A signed-in company account also keeps the choice on its record, so it follows the account to another device.
        if (tenancy()->initialized) {
            $user = $request->user('tenant');
            if ($user instanceof User) {
                $user->forceFill(['locale' => $data['locale']])->save();
            }
        }

        return back();
    }
}
