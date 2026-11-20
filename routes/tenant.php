<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Modules\Users\Http\Controllers\TenantAuthController;
use App\Modules\Tenants\Http\Controllers\TenantDashboardController;
use App\Modules\Organization\Http\Controllers\OrganizationController;
use App\Modules\Employees\Http\Controllers\EmployeeController;
use App\Modules\Employees\Http\Controllers\EmployeeAddressController;
use App\Modules\Employees\Http\Controllers\EmployeeEmergencyContactController;
use App\Modules\Employees\Http\Controllers\EmployeeIdentityRecordController;
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

            Route::prefix('/employees')->name('employees.')->group(function () {
                Route::controller(EmployeeController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{employee}/edit', 'edit')->whereNumber('employee')->name('edit');
                    Route::get('/{employee}', 'show')->whereNumber('employee')->name('show');
                    Route::put('/{employee}', 'update')->whereNumber('employee')->name('update');
                    Route::delete('/{employee}', 'destroy')->whereNumber('employee')->name('destroy');
                });

                Route::prefix('/{employee}/addresses')
                    ->whereNumber('employee')
                    ->name('addresses.')
                    ->controller(EmployeeAddressController::class)
                    ->group(function () {
                        Route::get('/', 'index')->name('index');
                        Route::post('/', 'store')->name('store');
                        Route::put('/{address}', 'update')->whereNumber('address')->name('update');
                        Route::delete('/{address}', 'destroy')->whereNumber('address')->name('destroy');
                    });

                Route::prefix('/{employee}/emergency-contacts')
                    ->whereNumber('employee')
                    ->name('emergency-contacts.')
                    ->controller(EmployeeEmergencyContactController::class)
                    ->group(function () {
                        Route::get('/', 'index')->name('index');
                        Route::post('/', 'store')->name('store');
                        Route::put('/{contact}', 'update')->whereNumber('contact')->name('update');
                        Route::delete('/{contact}', 'destroy')->whereNumber('contact')->name('destroy');
                    });

                Route::prefix('/{employee}/identity-records')
                    ->whereNumber('employee')
                    ->name('identity-records.')
                    ->controller(EmployeeIdentityRecordController::class)
                    ->group(function () {
                        Route::get('/', 'index')->name('index');
                        Route::post('/', 'store')->name('store');
                    });
            });

            Route::post('/logout', [TenantAuthController::class, 'destroy'])->name('tenant.logout');
        });
    });
