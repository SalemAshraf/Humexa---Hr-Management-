<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Modules\Users\Http\Controllers\PlatformAuthController;
use Inertia\Inertia;
use App\Modules\Tenants\Http\Controllers\PlatformDashboardController;
use App\Modules\Tenants\Http\Controllers\PlatformTenantController;

Route::post('/locale', [\App\Http\Controllers\LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale');

Route::get('/sitemap.xml', function () {
    $urls = [url('/'), url('/login'), url('/register')];
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $u) {
        $xml .= '<url><loc>'.e($u).'</loc></url>';
    }

    return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
});

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

        Route::get('/users', [\App\Modules\Tenants\Http\Controllers\PlatformInfoController::class, 'users'])->name('users.index');
        Route::get('/notifications', [\App\Modules\Tenants\Http\Controllers\PlatformInfoController::class, 'notifications'])->name('notifications.index');
        Route::get('/settings', [\App\Modules\Tenants\Http\Controllers\PlatformInfoController::class, 'settings'])->name('settings.index');

        Route::get('/plans', [\App\Modules\Plans\Http\Controllers\PlatformPlanController::class, 'index'])->name('plans.index');
        Route::post('/plans', [\App\Modules\Plans\Http\Controllers\PlatformPlanController::class, 'store'])->name('plans.store');
        Route::patch('/plans/{plan}', [\App\Modules\Plans\Http\Controllers\PlatformPlanController::class, 'update'])->name('plans.update');
        Route::patch('/companies/{tenant}/subscription', [\App\Modules\Plans\Http\Controllers\PlatformPlanController::class, 'subscription'])->name('companies.subscription');
        Route::patch('/companies/{tenant}/plan', [\App\Modules\Plans\Http\Controllers\PlatformPlanController::class, 'assign'])->name('companies.plan');

        Route::post('/logout', [PlatformAuthController::class, 'destroy'])
            ->name('logout');
    });
});

require __DIR__.'/auth.php';
