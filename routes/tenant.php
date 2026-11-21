<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Modules\Users\Http\Controllers\TenantAuthController;
use App\Modules\Tenants\Http\Controllers\TenantDashboardController;
use App\Modules\Organization\Http\Controllers\OrganizationController;
use App\Modules\Employees\Http\Controllers\EmployeeController;
use App\Modules\Attendance\Http\Controllers\TenantAttendanceDashboardController;
use App\Modules\Attendance\Http\Controllers\TenantAttendanceRecordsController;
use App\Modules\Employees\Http\Controllers\EmployeeAddressController;
use App\Modules\Employees\Http\Controllers\EmployeeEmergencyContactController;
use App\Modules\Employees\Http\Controllers\EmployeeIdentityRecordController;
use App\Modules\Employees\Http\Controllers\EmployeeDocumentTypeController;
use App\Modules\Employees\Http\Controllers\EmployeeDocumentController;
use App\Modules\Employees\Http\Controllers\EmployeeProfilePhotoController;
use App\Modules\Leave\Http\Controllers\LeaveController;
use App\Http\Middleware\EnsureTenantActive;

Route::domain('{tenant}.'.config('humexa.tenant_domain'))
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
            Route::get('/attendance', TenantAttendanceDashboardController::class)->middleware('tenant.can:attendance.view')->name('tenant.attendance.index');
            Route::get('/attendance/records', TenantAttendanceRecordsController::class)->middleware('tenant.can:attendance.view')->name('tenant.attendance.records');

            Route::prefix('/leave')->middleware('tenant.can:leave.view')->name('leave.')->controller(LeaveController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::put('/settings', 'updateSettings')->middleware('tenant.can:leave.manage')->name('settings.update');
                Route::post('/types', 'storeType')->middleware('tenant.can:leave.manage')->name('types.store');
                Route::put('/types/{type}', 'updateType')->whereNumber('type')->middleware('tenant.can:leave.manage')->name('types.update');
                Route::post('/requests', 'storeRequest')->middleware('tenant.can:leave.manage')->name('requests.store');
                Route::post('/requests/{leaveRequest}/review', 'review')->whereNumber('leaveRequest')->middleware('tenant.can:leave.approve')->name('requests.review');
                Route::post('/requests/{leaveRequest}/cancel', 'cancel')->whereNumber('leaveRequest')->middleware('tenant.can:leave.manage')->name('requests.cancel');
            });

            Route::prefix('/employee-document-types')
                ->middleware('tenant.can:employees.manage')
                ->name('employee-document-types.')
                ->controller(EmployeeDocumentTypeController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::put('/{type}', 'update')->whereNumber('type')->name('update');
                    Route::delete('/{type}', 'destroy')->whereNumber('type')->name('destroy');
                });

            Route::prefix('/organization')->middleware('tenant.can:employees.view,organization.manage')->name('organization.')->controller(OrganizationController::class)->group(function () {
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

            Route::prefix('/employees')->middleware('tenant.can:employees.view,employees.manage')->name('employees.')->group(function () {
                Route::controller(EmployeeController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/archived', 'archived')->name('archived');
                    Route::post('/{employee}/restore', 'restore')->whereNumber('employee')->name('restore');
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

                Route::prefix('/{employee}/profile-photo')
                    ->whereNumber('employee')
                    ->name('profile-photo.')
                    ->controller(EmployeeProfilePhotoController::class)
                    ->group(function () {
                        Route::get('/', 'show')->name('show');
                        Route::post('/', 'store')->name('store');
                        Route::post('/remove', 'destroy')->name('remove');
                    });

                Route::prefix('/{employee}/documents')
                    ->whereNumber('employee')
                    ->name('documents.')
                    ->controller(EmployeeDocumentController::class)
                    ->group(function () {
                        Route::get('/', 'index')->name('index');
                        Route::post('/', 'store')->name('store');
                        Route::get('/{document}/download', 'download')->whereNumber('document')->name('download');
                        Route::get('/{document}/preview', 'preview')->whereNumber('document')->name('preview');
                        Route::post('/{document}/archive', 'archive')->whereNumber('document')->name('archive');
                        Route::post('/{document}/delete', 'destroy')->whereNumber('document')->name('delete');
                    });
            });

            Route::post('/logout', [TenantAuthController::class, 'destroy'])->name('tenant.logout');
        });
    });
