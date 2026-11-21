<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Modules\Users\Http\Controllers\PlatformAuthController;
use Inertia\Inertia;
use App\Modules\Tenants\Http\Controllers\PlatformDashboardController;
use App\Modules\Tenants\Http\Controllers\PlatformTenantController;

Route::post('/locale', [\App\Http\Controllers\LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('platform')->name('platform.')->group(function () {
    Route::get('/login', [PlatformAuthController::class, 'create'])
        ->name('login');

    Route::post('/login', [PlatformAuthController::class, 'store'])
        ->name('login.store');

    Route::middleware(['auth:platform', 'platform.user'])->group(function () {
        Route::get('/dashboard', PlatformDashboardController::class)
            ->name('dashboard');

        Route::get('/companies', [PlatformTenantController::class, 'index'])
            ->name('companies.index');

        Route::patch('/companies/{tenant}/suspend', [PlatformTenantController::class, 'suspend'])
            ->name('companies.suspend');

        Route::patch('/companies/{tenant}/activate', [PlatformTenantController::class, 'activate'])
            ->name('companies.activate');

        Route::get('/activity-logs', [PlatformTenantController::class, 'activityLogs'])
            ->name('activity-logs.index');

        Route::post('/logout', [PlatformAuthController::class, 'destroy'])
            ->name('logout');
    });
});

require __DIR__.'/auth.php';
