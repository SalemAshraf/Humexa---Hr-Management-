<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Middleware\EnsureTenantActive;
use App\Modules\Attendance\Http\Controllers\Api\V1\AttendanceController;
use App\Modules\Attendance\Http\Controllers\Api\V1\AttendanceWorkflowController;
use App\Modules\Attendance\Http\Controllers\Api\V1\MobileAuthController;
use App\Modules\Leave\Http\Controllers\Api\V1\LeaveApiController;

Route::domain('{tenant}.'.config('humexa.tenant_domain'))->middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    EnsureTenantActive::class,
])->prefix('v1')->group(function(){
    Route::post('/auth/login',[MobileAuthController::class,'login'])->middleware('throttle:10,1');
    Route::middleware(['auth:sanctum', 'abilities:attendance:mobile'])->group(function(){
        Route::post('/auth/logout',[MobileAuthController::class,'logout']);
        Route::get('/leave',[LeaveApiController::class,'index']);
        Route::post('/leave',[LeaveApiController::class,'store'])->middleware('throttle:20,1');
        Route::post('/leave/{leaveRequest}/cancel',[LeaveApiController::class,'cancel'])->whereNumber('leaveRequest');
        Route::prefix('attendance')->group(function(){
            Route::get('/policy',[AttendanceController::class,'policy']);
            Route::get('/status',[AttendanceController::class,'status']);
            Route::get('/history',[AttendanceController::class,'history']);
            Route::post('/check-in',[AttendanceController::class,'checkIn']);
            Route::post('/check-out',[AttendanceController::class,'checkOut']);
            Route::post('/breaks/start',[AttendanceWorkflowController::class,'startBreak']);
            Route::post('/breaks/end',[AttendanceWorkflowController::class,'endBreak']);
            Route::post('/corrections',[AttendanceWorkflowController::class,'requestCorrection']);
            Route::get('/corrections',[AttendanceWorkflowController::class,'corrections']);
            Route::post('/overtime-requests',[AttendanceWorkflowController::class,'requestOvertime']);
            Route::get('/overtime-requests',[AttendanceWorkflowController::class,'overtimeRequests']);
        });
    });
});
