<?php

declare(strict_types=1);

namespace App\\Modules\\Attendance\\Http\\Controllers;

use App\\Http\\Controllers\\Controller;
use App\\Modules\\Attendance\\Models\\AttendancePolicy;
use App\\Modules\\Attendance\\Models\\AttendanceRecord;
use App\\Modules\\Attendance\\Models\\AttendanceSession;
use App\\Modules\\Employees\\Models\\Employee;
use Illuminate\\Http\\Request;
use Inertia\\Inertia;
use Inertia\\Response;

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

        $employees = Employee::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('employment_status', [
                Employee::STATUS_RESIGNED,
                Employee::STATUS_TERMINATED,
            ])
            ->with('department:id,name')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString();

        $employeeIds = $employees->getCollection()->modelKeys();

        $records = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('attendance_date', $today)
            ->whereIn('employee_id', $employeeIds)
            ->with(['sessions.breaks'])
            ->get()
            ->keyBy('employee_id');

        $employees->through(function (Employee $employee) use ($records): array {
            $record = $records->get($employee->id);
            $sessions = $record?->sessions ?? collect();
            $activeSession = $sessions->first(
                fn (AttendanceSession $session): bool => $session->check_in_at !== null
                    && $session->check_out_at === null
            );

            return [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->full_name,
                'department' => $employee->department?->name,
                'employment_status' => $employee->employment_status,
                'attendance' => [
                    'state' => $activeSession
                        ? 'checked_in'
                        : ($record ? 'checked_out' : 'not_recorded'),
                    'worked_minutes' => (int) ($record?->total_worked_minutes ?? 0),
                    'break_minutes' => (int) ($record?->total_break_minutes ?? 0),
                    'sessions' => $sessions->map(fn (AttendanceSession $session): array => [
                        'id' => $session->id,
                        'session_number' => $session->session_number,
                        'check_in_at' => $session->check_in_at?->toIso8601String(),
                        'check_out_at' => $session->check_out_at?->toIso8601String(),
                        'worked_minutes' => (int) $session->worked_minutes,
                        'break_minutes' => (int) $session->break_minutes,
                        'active_break' => $session->breaks->contains(
                            fn ($break): bool => $break->ended_at === null
                        ),
                    ])->values(),
                ],
            ];
        });

        $policy = AttendancePolicy::query()
            ->where('tenant_id', $tenantId)
            ->first();

        return Inertia::render('Company/Attendance/Index', [
            'date' => $today,
            'employees' => $employees,
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
