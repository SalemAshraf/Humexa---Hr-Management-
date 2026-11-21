<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\Request;

class AttendancePolicyApiController extends Controller
{
    public function show(Request $request)
    {
        abort_unless(tenancy()->initialized,404);
        $user=$request->user();
        abort_unless($user && $user->account_type==='tenant' && (string)$user->tenant_id === (string)tenant('id'),403);
        Employee::query()->where('tenant_id',tenant('id'))->where('user_id',$user->id)->firstOrFail();
        $policy=AttendancePolicy::query()->where('tenant_id',tenant('id'))->firstOrFail();
        return response()->json(['data'=>$policy->only([
            'mobile_checkin_enabled','location_verification_enabled','selfie_verification_enabled',
            'fingerprint_device_enabled','attendance_correction_enabled','overtime_requires_approval',
            'offline_attendance_enabled','location_accuracy_limit','allowed_location_radius',
            'late_grace_minutes','minimum_break_minutes',
        ])]);
    }
}