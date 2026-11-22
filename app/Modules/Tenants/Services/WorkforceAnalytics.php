<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Services;

use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSession;
use App\Modules\Employees\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Company-wide workforce numbers for the dashboard. Every query is scoped to
 * one company id. Callers decide who may see which block.
 */
class WorkforceAnalytics
{
    private const LEAVING = [Employee::STATUS_RESIGNED, Employee::STATUS_TERMINATED];

    /**
     * @return array<string, mixed>
     */
    public function headcount(string $tenantId, ?Carbon $now = null): array
    {
        $now ??= now();

        $byStatus = Employee::query()
            ->where('tenant_id', $tenantId)
            ->select('employment_status', DB::raw('count(*) as total'))
            ->groupBy('employment_status')
            ->pluck('total', 'employment_status')
            ->map(fn ($n) => (int) $n)
            ->all();

        $current = array_sum(array_diff_key($byStatus, array_flip(self::LEAVING)));

        $byDepartment = Employee::query()
            ->where('employees.tenant_id', $tenantId)
            ->whereNotIn('employees.employment_status', self::LEAVING)
            ->leftJoin('departments', function ($join) use ($tenantId) {
                $join->on('departments.id', '=', 'employees.department_id')
                    ->where('departments.tenant_id', '=', $tenantId);
            })
            ->select(DB::raw("coalesce(departments.name, 'Unassigned') as name"), DB::raw('count(*) as total'))
            ->groupBy('departments.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total])
            ->all();

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = $now->copy()->startOfMonth()->subMonths($i);
            $months[$start->format('Y-m')] = ['month' => $start->format('M Y'), 'hires' => 0, 'exits' => 0];
        }

        $first = $now->copy()->startOfMonth()->subMonths(5)->toDateString();

        Employee::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('hire_date')
            ->whereDate('hire_date', '>=', $first)
            ->pluck('hire_date')
            ->each(function ($date) use (&$months) {
                $key = Carbon::parse($date)->format('Y-m');
                if (isset($months[$key])) {
                    $months[$key]['hires']++;
                }
            });

        Employee::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('employment_status', self::LEAVING)
            ->whereNotNull('termination_date')
            ->whereDate('termination_date', '>=', $first)
            ->pluck('termination_date')
            ->each(function ($date) use (&$months) {
                $key = Carbon::parse($date)->format('Y-m');
                if (isset($months[$key])) {
                    $months[$key]['exits']++;
                }
            });

        return [
            'current' => $current,
            'on_probation' => (int) ($byStatus[Employee::STATUS_PROBATION] ?? 0),
            'on_leave' => (int) ($byStatus[Employee::STATUS_ON_LEAVE] ?? 0),
            'by_status' => $byStatus,
            'by_department' => $byDepartment,
            'monthly' => array_values($months),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function attendanceToday(string $tenantId, ?Carbon $now = null): array
    {
        $today = \App\Modules\Settings\Support\CompanyClock::today($tenantId, $now);

        $expected = Employee::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('employment_status', [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION])
            ->count();

        $present = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('attendance_date', $today)
            ->distinct('employee_id')
            ->count('employee_id');

        $checkedIn = AttendanceSession::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->distinct('employee_id')
            ->count('employee_id');

        $late = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('attendance_date', $today)
            ->where('late_minutes', '>', 0)
            ->count();

        return [
            'expected' => $expected,
            'present' => $present,
            'checked_in_now' => $checkedIn,
            'late' => $late,
            'absent_or_missing' => max(0, $expected - $present),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function leave(string $tenantId, ?Carbon $now = null): array
    {
        $today = \App\Modules\Settings\Support\CompanyClock::today($tenantId, $now);

        return [
            'pending' => LeaveRequest::query()->where('tenant_id', $tenantId)->where('status', LeaveRequest::PENDING)->count(),
            'on_leave_today' => LeaveRequest::query()->where('tenant_id', $tenantId)->where('status', LeaveRequest::APPROVED)
                ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count(),
        ];
    }
}
