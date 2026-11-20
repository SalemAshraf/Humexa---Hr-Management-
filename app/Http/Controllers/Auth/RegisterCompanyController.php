<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterCompanyRequest;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegisterCompanyController extends Controller
{
    /**
     * Display the company registration page.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Register a company and its first administrator.
     */
    public function store(
        RegisterCompanyRequest $request,
        RegisterCompanyAction $action
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $result = $action->execute(
                companyName: $validated['company_name'],
                adminName: $validated['name'],
                email: $validated['email'],
                password: $validated['password'],
            );

            return redirect()
                ->back()
                ->with('status', 'Company registered successfully.')
                ->with('registered_domain', $result['domain']);

        } catch (Throwable $exception) {
            Log::error('Company registration failed.', [
                'exception' => $exception,
            ]);

            return redirect()
                ->back()
                ->withInput($request->safe()->except('password'))
                ->withErrors([
                    'registration' => 'Unable to create your company account. Please try again.',
                ]);
        }
    }
}
