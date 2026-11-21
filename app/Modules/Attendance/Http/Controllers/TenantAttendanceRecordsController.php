<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TenantAttendanceRecordsController extends Controller
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

        abort_unless($user->can('attendance.view'), 403);

        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $scope = $this->scopeIds($tenantId, $user);

        $query = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->when($scope !== null, fn ($q) => $q->whereIn('employee_id', $scope))
            ->with([
                'employee:id,tenant_id,employee_number,first_name,middle_name,last_name,department_id,branch_id',
                'employee.department:id,name',
                'employee.branch:id,name',
            ]);

        $date = $filters['date'] ?? now()->toDateString();
        $query->whereDate('attendance_date', $date);

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->whereHas('employee', function ($employeeQuery) use ($search): void {
                $employeeQuery
                    ->where('employee_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $records = $query
            ->orderBy('attendance_date')
            ->orderBy('employee_id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AttendanceRecord $record): array => [
                'id' => $record->id,
                'attendance_date' => $record->attendance_date?->toDateString(),
                'status' => $record->status,
                'total_worked_minutes' => $record->total_worked_minutes,
                'total_break_minutes' => $record->total_break_minutes,
                'late_minutes' => $record->late_minutes,
                'early_leave_minutes' => $record->early_leave_minutes,
                'overtime_minutes' => $record->overtime_minutes,
                'employee' => $record->employee ? [
                    'id' => $record->employee->id,
                    'employee_number' => $record->employee->employee_number,
                    'full_name' => $record->employee->full_name,
                    'department' => $record->employee->department?->name,
                    'branch' => $record->employee->branch?->name,
                ] : null,
            ]);

        return Inertia::render('Company/Attendance/Records', [
            'records' => $records,
            'filters' => [
                'date' => $date,
                'search' => $filters['search'] ?? '',
            ],
        ]);
    }

    /** Null means the whole company. Without attendance.manage a person sees only their direct reports. */
    private function scopeIds(string $tenantId, $user): ?array
    {
        if ($user->can('attendance.manage')) {
            return null;
        }
        $own = \App\Modules\Employees\Models\Employee::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->value('id');
        if ($own === null) {
            return [];
        }

        return \App\Modules\Employees\Models\Employee::query()->where('tenant_id', $tenantId)->where('manager_id', $own)->pluck('id')->all();
    }
}
