<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSession;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantAttendanceDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(tenancy()->initialized, 404);

        $tenantId = (string) tenant()->getTenantKey();
        $user = $request->user('tenant');

        abort_unless(
            $user
            && $user->account_type === 'tenant'
            && $user->tenant_id !== null
            && hash_equals($tenantId, (string) $user->tenant_id),
            403
        );

        $today = now()->toDateString();

        $policy = AttendancePolicy::query()
            ->where('tenant_id', $tenantId)
            ->first();

        return Inertia::render('Company/Attendance/Index', [
            'date' => $today,
            'stats' => [
                'total_employees' => Employee::query()
                    ->where('tenant_id', $tenantId)
                    ->whereNotIn('employment_status', [
                        Employee::STATUS_RESIGNED,
                        Employee::STATUS_TERMINATED,
                    ])
                    ->count(),
                'with_records' => AttendanceRecord::query()
                    ->where('tenant_id', $tenantId)
                    ->whereDate('attendance_date', $today)
                    ->distinct('employee_id')
                    ->count('employee_id'),
                'currently_checked_in' => AttendanceSession::query()
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('check_in_at')
                    ->whereNull('check_out_at')
                    ->distinct('employee_id')
                    ->count('employee_id'),
            ],
            'policy' => [
                'mobile_checkin_enabled' => (bool) ($policy?->mobile_checkin_enabled ?? false),
                'manual_attendance_enabled' => (bool) ($policy?->manual_attendance_enabled ?? false),
                'location_verification_enabled' => (bool) ($policy?->location_verification_enabled ?? false),
                'selfie_verification_enabled' => (bool) ($policy?->selfie_verification_enabled ?? false),
                'attendance_correction_enabled' => (bool) ($policy?->attendance_correction_enabled ?? false),
                'overtime_requires_approval' => (bool) ($policy?->overtime_requires_approval ?? true),
            ],
        ]);
    }
}
