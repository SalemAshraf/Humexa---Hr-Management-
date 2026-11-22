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
use App\Modules\Attendance\Http\Controllers\TenantAttendanceRequestsController;
use App\Modules\Employees\Http\Controllers\EmployeeAddressController;
use App\Modules\Employees\Http\Controllers\EmployeeEmergencyContactController;
use App\Modules\Employees\Http\Controllers\EmployeeIdentityRecordController;
use App\Modules\Employees\Http\Controllers\EmployeeDocumentTypeController;
use App\Modules\Employees\Http\Controllers\EmployeeDocumentController;
use App\Modules\Employees\Http\Controllers\EmployeeProfilePhotoController;
use App\Modules\Leave\Http\Controllers\LeaveController;
use App\Modules\Users\Http\Controllers\TenantTeamController;
use App\Modules\Users\Http\Controllers\TenantPasswordController;
use App\Modules\Recruitment\Http\Controllers\RecruitmentController;
use App\Modules\Performance\Http\Controllers\PerformanceController;
use App\Modules\Payroll\Http\Controllers\PayrollController;
use App\Modules\Reports\Http\Controllers\ReportsController;
use App\Modules\Settings\Http\Controllers\SettingsController;
use App\Modules\Notifications\Http\Controllers\NotificationsController;
use App\Modules\Search\Http\Controllers\SearchController;
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

        Route::post('/locale', [\App\Http\Controllers\LocaleController::class, 'update'])->middleware('throttle:30,1')->name('tenant.locale');
        Route::get('/login', [TenantAuthController::class, 'create'])->name('tenant.login');
        Route::post('/login', [TenantAuthController::class, 'store'])->name('tenant.login.store');

        // AI connector endpoints for this company host. Read-only. Tokens only work on the host they were approved on.
        Route::get('/.well-known/oauth-protected-resource/{any?}', [\App\Modules\AiConnector\Http\Controllers\OauthController::class, 'resourceMetadata'])->where('any', '.*');
        Route::get('/.well-known/oauth-authorization-server/{any?}', [\App\Modules\AiConnector\Http\Controllers\OauthController::class, 'serverMetadata'])->where('any', '.*');
        Route::post('/oauth/register', [\App\Modules\AiConnector\Http\Controllers\OauthController::class, 'register'])->middleware('throttle:20,1');
        Route::post('/oauth/token', [\App\Modules\AiConnector\Http\Controllers\OauthController::class, 'token'])->middleware('throttle:60,1');
        Route::post('/oauth/revoke', [\App\Modules\AiConnector\Http\Controllers\OauthController::class, 'revoke'])->middleware('throttle:60,1');
        Route::post('/mcp', [\App\Modules\AiConnector\Http\Controllers\McpController::class, 'handle'])->middleware('throttle:60,1')->name('mcp');

        Route::middleware(['auth:tenant', 'tenant.user', 'tenant.password'])->group(function () {
            // Read-only AI connector: the signed-in person approves an app, and can disconnect it again.
            Route::get('/oauth/authorize', [\App\Modules\AiConnector\Http\Controllers\OauthController::class, 'authorizeForm'])->name('oauth.authorize');
            Route::post('/oauth/authorize', [\App\Modules\AiConnector\Http\Controllers\OauthController::class, 'decide'])->name('oauth.decide');
            Route::get('/ai-connections', [\App\Modules\AiConnector\Http\Controllers\ConnectionsController::class, 'index'])->name('ai.connections');
            Route::delete('/ai-connections/{family}', [\App\Modules\AiConnector\Http\Controllers\ConnectionsController::class, 'destroy'])->name('ai.disconnect');

            Route::get('/password/change', [TenantPasswordController::class, 'create'])->name('tenant.password.edit');
            Route::post('/password/change', [TenantPasswordController::class, 'update'])->name('tenant.password.update');

            Route::get('/dashboard', TenantDashboardController::class)->name('tenant.dashboard');
            Route::get('/account', [\App\Modules\Users\Http\Controllers\TenantAccountController::class, 'edit'])->name('account.edit');
            Route::put('/account', [\App\Modules\Users\Http\Controllers\TenantAccountController::class, 'update'])->middleware('throttle:20,1')->name('account.update');
            Route::get('/attendance', TenantAttendanceDashboardController::class)->middleware('tenant.can:attendance.view')->name('tenant.attendance.index');
            Route::get('/attendance/records', TenantAttendanceRecordsController::class)->middleware('tenant.can:attendance.view')->name('tenant.attendance.records');

            Route::post('/attendance/manual', [\App\Modules\Attendance\Http\Controllers\TenantManualAttendanceController::class, 'store'])->middleware(['tenant.can:attendance.manage', 'throttle:60,1'])->name('tenant.attendance.manual');
            Route::get('/attendance/shifts', [\App\Modules\Attendance\Http\Controllers\TenantShiftController::class, 'index'])->middleware('tenant.can:attendance.manage')->name('tenant.attendance.shifts');
            Route::post('/attendance/shifts', [\App\Modules\Attendance\Http\Controllers\TenantShiftController::class, 'store'])->middleware('tenant.can:attendance.manage')->name('tenant.attendance.shifts.store');
            Route::put('/attendance/shifts/assign', [\App\Modules\Attendance\Http\Controllers\TenantShiftController::class, 'assign'])->middleware('tenant.can:attendance.manage')->name('tenant.attendance.shifts.assign');
            Route::put('/attendance/shifts/{shift}', [\App\Modules\Attendance\Http\Controllers\TenantShiftController::class, 'update'])->middleware('tenant.can:attendance.manage')->name('tenant.attendance.shifts.update');
            Route::delete('/attendance/shifts/{shift}', [\App\Modules\Attendance\Http\Controllers\TenantShiftController::class, 'destroy'])->middleware('tenant.can:attendance.manage')->name('tenant.attendance.shifts.destroy');
            Route::put('/attendance/policy', [\App\Modules\Attendance\Http\Controllers\TenantManualAttendanceController::class, 'updatePolicy'])->middleware('tenant.can:attendance.manage')->name('tenant.attendance.policy');

            Route::prefix('/attendance/requests')->middleware('tenant.can:attendance.manage')->name('tenant.attendance.requests.')->controller(TenantAttendanceRequestsController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/correction/{correction}/review', 'reviewCorrection')->whereNumber('correction')->name('correction');
                Route::post('/overtime/{overtime}/review', 'reviewOvertime')->whereNumber('overtime')->name('overtime');
            });

            Route::prefix('/recruitment')->middleware('tenant.can:recruitment.view,recruitment.manage')->name('recruitment.')->controller(RecruitmentController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/jobs', 'storeJob')->name('jobs.store');
                Route::put('/jobs/{job}', 'updateJob')->whereNumber('job')->name('jobs.update');
                Route::delete('/jobs/{job}', 'destroyJob')->whereNumber('job')->name('jobs.destroy');
                Route::post('/candidates', 'storeCandidate')->name('candidates.store');
                Route::put('/candidates/{candidate}', 'updateCandidate')->whereNumber('candidate')->name('candidates.update');
                Route::post('/candidates/{candidate}/move', 'moveCandidate')->whereNumber('candidate')->name('candidates.move');
                Route::post('/candidates/{candidate}/hire', 'hire')->whereNumber('candidate')->name('candidates.hire');
                Route::delete('/candidates/{candidate}', 'destroyCandidate')->whereNumber('candidate')->name('candidates.destroy');
            });

            Route::prefix('/performance')->middleware('tenant.can:performance.view')->name('performance.')->controller(PerformanceController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/cycles', 'storeCycle')->name('cycles.store');
                Route::put('/cycles/{cycle}', 'updateCycle')->whereNumber('cycle')->name('cycles.update');
                Route::delete('/cycles/{cycle}', 'destroyCycle')->whereNumber('cycle')->name('cycles.destroy');
                Route::post('/reviews', 'storeReview')->name('reviews.store');
                Route::put('/reviews/{review}', 'updateReview')->whereNumber('review')->name('reviews.update');
                Route::post('/reviews/{review}/finalize', 'finalize')->whereNumber('review')->name('reviews.finalize');
                Route::post('/reviews/{review}/reopen', 'reopen')->whereNumber('review')->name('reviews.reopen');
                Route::delete('/reviews/{review}', 'destroyReview')->whereNumber('review')->name('reviews.destroy');
            });

            Route::prefix('/payroll')->middleware('tenant.can:payroll.view')->name('payroll.')->controller(PayrollController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/runs/{run}', 'showRun')->whereNumber('run')->name('runs.show');
                Route::get('/runs/{run}/export', 'export')->whereNumber('run')->name('runs.export');
                Route::middleware('tenant.can:payroll.manage')->group(function () {
                    Route::put('/rules', 'updateRules')->name('rules.update');
                    Route::post('/rules/confirm', 'confirmRules')->name('rules.confirm');
                    Route::post('/rules/egypt-template', 'loadEgyptTemplate')->name('rules.template');
                    Route::post('/employees/{employee}/compensation', 'storeCompensation')->whereNumber('employee')->name('compensation.store');
                    Route::get('/runs/{run}/transfer-list', 'exportBank')->whereNumber('run')->name('runs.transfer');
                    Route::put('/settings', 'updateSettings')->name('settings.update');
                    Route::post('/advances', 'storeAdvance')->name('advances.store');
                    Route::post('/advances/{advance}/cancel', 'cancelAdvance')->whereNumber('advance')->name('advances.cancel');
                    Route::post('/runs', 'storeRun')->name('runs.store');
                    Route::post('/runs/{run}/recalculate', 'recalculate')->whereNumber('run')->name('runs.recalculate');
                    Route::post('/runs/{run}/approve', 'approve')->whereNumber('run')->name('runs.approve');
                    Route::delete('/runs/{run}', 'destroyRun')->whereNumber('run')->name('runs.destroy');
                    Route::post('/runs/{run}/adjustments', 'storeAdjustment')->whereNumber('run')->name('adjustments.store');
                    Route::delete('/runs/{run}/adjustments/{adjustment}', 'destroyAdjustment')->whereNumber('run')->whereNumber('adjustment')->name('adjustments.destroy');
                });
            });

            Route::prefix('/reports')->middleware('tenant.can:reports.view')->name('reports.')->controller(ReportsController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/export', 'export')->name('export');
            });

            Route::prefix('/settings')->middleware('tenant.can:settings.manage')->name('settings.')->controller(SettingsController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::put('/', 'update')->name('update');
            });

            Route::get('/documents/expiry', [\App\Modules\Employees\Http\Controllers\DocumentExpiryController::class, 'index'])->middleware('tenant.can:employees.manage')->name('documents.expiry');
            Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
            Route::prefix('/me')->middleware(\App\Http\Middleware\ExcludeOwnerFromSelfService::class)->name('me.')->controller(\App\Modules\SelfService\Http\Controllers\SelfServiceController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/attendance/check-in', 'checkIn')->middleware('throttle:30,1')->name('attendance.check-in');
                Route::post('/attendance/check-out', 'checkOut')->middleware('throttle:30,1')->name('attendance.check-out');
                Route::post('/leave', 'storeLeave')->middleware('throttle:20,1')->name('leave.store');
                Route::post('/leave/{leaveRequest}/cancel', 'cancelLeave')->whereNumber('leaveRequest')->name('leave.cancel');
            });
            Route::get('/search', [SearchController::class, 'index'])->name('search.index');

            Route::prefix('/team')->middleware('tenant.can:roles.manage')->name('team.')->controller(TenantTeamController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('/{account}/role', 'updateRole')->whereNumber('account')->name('role');
                Route::put('/{account}/active', 'setActive')->whereNumber('account')->name('active');
                Route::put('/{account}/reset-access', 'resetAccess')->whereNumber('account')->name('reset');
            });

            Route::prefix('/leave')->middleware('tenant.can:leave.view')->name('leave.')->controller(LeaveController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::put('/settings', 'updateSettings')->middleware('tenant.can:leave.manage')->name('settings.update');
                Route::post('/types', 'storeType')->middleware('tenant.can:leave.manage')->name('types.store');
                Route::put('/types/{type}', 'updateType')->whereNumber('type')->middleware('tenant.can:leave.manage')->name('types.update');
                Route::delete('/types/{type}', 'destroyType')->whereNumber('type')->middleware('tenant.can:leave.manage')->name('types.destroy');
                Route::put('/requests/{leaveRequest}', 'updateRequest')->whereNumber('leaveRequest')->middleware('tenant.can:leave.manage')->name('requests.update');
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

            Route::prefix('/organization/import/{entity}')->middleware('tenant.can:organization.manage')->name('organization.import.')->controller(\App\Modules\Organization\Http\Controllers\OrganizationImportController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/template', 'template')->name('template');
                Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
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

            Route::get('/data-export', [\App\Modules\Tenants\Http\Controllers\DataExportController::class, 'index'])->name('data-export.index');
            Route::get('/data-export/{entity}', [\App\Modules\Tenants\Http\Controllers\DataExportController::class, 'download'])->middleware('throttle:20,1')->name('data-export.download');

            Route::prefix('/employees')->middleware('tenant.can:employees.view,employees.manage')->name('employees.')->group(function () {
                Route::controller(\App\Modules\Employees\Http\Controllers\EmployeeImportController::class)->prefix('/import')->middleware('tenant.can:employees.manage')->name('import.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/template', 'template')->name('template');
                    Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
                });

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
                    ->middleware('tenant.can:employees.manage') // personal data: HR only, managers get no access
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
                    ->middleware('tenant.can:employees.manage') // personal data: HR only, managers get no access
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
                    ->middleware('tenant.can:employees.manage') // personal data: HR only, managers get no access
                    ->name('identity-records.')
                    ->controller(EmployeeIdentityRecordController::class)
                    ->group(function () {
                        Route::get('/', 'index')->name('index');
                        Route::post('/', 'store')->name('store');
                        Route::put('/{identity}', 'update')->whereNumber('identity')->name('update');
                        Route::delete('/{identity}', 'destroy')->whereNumber('identity')->name('destroy');
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
                    ->middleware('tenant.can:employees.manage') // personal data: HR only, managers get no access
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
