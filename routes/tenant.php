<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Modules\Users\Http\Controllers\TenantAuthController;
use App\Http\Middleware\EnsureTenantActive;
use Inertia\Inertia;

Route::domain('{tenant}.humexa.test')
    ->middleware([
        'web',
        InitializeTenancyByDomain::class,
        PreventAccessFromCentralDomains::class,
        EnsureTenantActive::class,
    ])
    ->group(function () {
        Route::get('/', function () {
            return 'Tenant ID: ' . tenant('id');
        })->name('tenant.home');
        

// Public tenant authentication routes
Route::get('/login', [TenantAuthController::class, 'create'])
    ->name('tenant.login');

Route::post('/login', [TenantAuthController::class, 'store'])
    ->name('tenant.login.store');

// Protected tenant routes
Route::middleware(['auth:tenant', 'tenant.user'])->group(function () {
    Route::get('/dashboard', TenantDashboardController::class)
        ->name('tenant.dashboard');

    Route::post('/logout', [TenantAuthController::class, 'destroy'])
        ->name('tenant.logout');
});
    });
