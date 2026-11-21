<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Middleware\EnsureTenantActive;
use App\Modules\Attendance\Http\Controllers\Api\V1\AttendanceController;

Route::domain('{tenant}.humexa.test')->middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    EnsureTenantActive::class,
])->prefix('v1')->group(function(){
    Route::middleware('auth:sanctum')->prefix('attendance')->group(function(){
        Route::get('/policy',[AttendanceController::class,'policy']);
        Route::get('/status',[AttendanceController::class,'status']);
        Route::get('/history',[AttendanceController::class,'history']);
        Route::post('/check-in',[AttendanceController::class,'checkIn']);
        Route::post('/check-out',[AttendanceController::class,'checkOut']);
    });
});