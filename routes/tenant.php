<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Modules\Users\Http\Controllers\TenantAuthController;
use App\Modules\Tenants\Http\Controllers\TenantDashboardController;
use App\Modules\Organization\Http\Controllers\OrganizationController;
use App\Modules\Employees\Http\Controllers\EmployeeController;
use App\Http\Middleware\EnsureTenantActive;

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

        Route::get('/login', [TenantAuthController::class, 'create'])->name('tenant.login');
        Route::post('/login', [TenantAuthController::class, 'store'])->name('tenant.login.store');

        Route::middleware(['auth:tenant', 'tenant.user'])->group(function () {
            Route::get('/dashboard', TenantDashboardController::class)->name('tenant.dashboard');

            Route::prefix('/organization')->name('organization.')->controller(OrganizationController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/branches', 'storeBranch')->name('branches.store');
                Route::put('/branches/{branch}', 'updateBranch')->whereNumber('branch')->name('branches.update');
                Route::delete('/branches/{branch}', 'destroyBranch')->whereNumber('branch')->name('branches.destroy');
                Route::post('/departments', 'storeDepartment')->name('departments.store');
                Route::put('/departments/{department}', 'updateDepartment')->whereNumber('department')->name('departments.update');
                Route::delete('/departments/{department}', 'destroyDepartment')->whereNumber('department')->name('departments.destroy');
                Route::post('/positions', 'storePosition')->name('positions.store');
                Route::put('/positions/{position}', 'updatePosition')->whereNumber('position')->name('positions.update');
                Route::delete('/positions/{position}', 'destroyPosition')->whereNumber('position')->name('positions.destroy');
            });

            Route::prefix('/employees')->name('employees.')->controller(EmployeeController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{employee}/edit', 'edit')->whereNumber('employee')->name('edit');
                Route::get('/{employee}', 'show')->whereNumber('employee')->name('show');
                Route::put('/{employee}', 'update')->whereNumber('employee')->name('update');
                Route::delete('/{employee}', 'destroy')->whereNumber('employee')->name('destroy');
            });

            Route::post('/logout', [TenantAuthController::class, 'destroy'])->name('tenant.logout');
        });
    });
