<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithTenant;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Employees\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports of company records. Each export needs the manage permission of its
 * section (HR admin and owner by default), is limited to the caller's company, and
 * lists a fixed set of columns: no ID numbers, addresses, documents, salaries or logins.
 */
class DataExportController extends Controller
{
    use InteractsWithTenant;

    private const ENTITIES = [
        'employees' => ['permission' => 'employees.manage', 'label' => 'Employees'],
        'leave' => ['permission' => 'leave.manage', 'label' => 'Leave requests'],
        'attendance' => ['permission' => 'attendance.manage', 'label' => 'Attendance records'],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user('tenant');
        abort_unless(collect(self::ENTITIES)->contains(fn ($e) => $user?->can($e['permission'])), 403);

        return Inertia::render('Company/DataExport', [
            'exports' => collect(self::ENTITIES)->filter(fn ($e) => $user?->can($e['permission']))
                ->map(fn ($e, $key) => ['key' => $key, 'label' => $e['label']])->values(),
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $entity = (string) $request->route('entity');
        abort_unless(isset(self::ENTITIES[$entity]), 404);
        abort_unless($request->user('tenant')?->can(self::ENTITIES[$entity]['permission']), 403);

        $tenantId = $this->tenantId();
        [$header, $rows] = match ($entity) {
            'employees' => $this->employees($tenantId),
            'leave' => $this->leave($tenantId),
            'attendance' => $this->attendance($tenantId),
        };

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row));
            }
            fclose($out);
        }, sprintf('afrad-%s-%s.csv', $entity, now()->toDateString()), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops spreadsheet programs from running a cell as a formula. */
    public static function safe(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }

    private function employees(string $tenantId): array
    {
        $rows = Employee::query()->where('tenant_id', $tenantId)->with(['department:id,name', 'position:id,name'])
            ->lazyById(1000)->map(fn (Employee $e) => [
                $e->employee_number, $e->first_name, $e->last_name, $e->work_email, $e->phone,
                optional($e->hire_date)->toDateString(), $e->employment_status, $e->department?->name, $e->position?->name,
            ]);

        return [['employee_number', 'first_name', 'last_name', 'work_email', 'phone', 'hire_date', 'status', 'department', 'position'], $rows];
    }

    private function leave(string $tenantId): array
    {
        $rows = LeaveRequest::query()->where('tenant_id', $tenantId)->with(['employee:id,employee_number,first_name,last_name', 'type:id,name'])
            ->lazyByIdDesc(1000)->map(fn (LeaveRequest $r) => [
                $r->employee?->employee_number, trim(($r->employee?->first_name ?? '').' '.($r->employee?->last_name ?? '')), $r->type?->name,
                optional($r->start_date)->toDateString(), optional($r->end_date)->toDateString(), $r->days, $r->status,
            ]);

        return [['employee_number', 'employee', 'leave_type', 'start_date', 'end_date', 'days', 'status'], $rows];
    }

    private function attendance(string $tenantId): array
    {
        $rows = AttendanceRecord::query()->where('tenant_id', $tenantId)->with('employee:id,employee_number,first_name,last_name')
            ->lazyByIdDesc(1000)->map(fn (AttendanceRecord $r) => [
                $r->employee?->employee_number, trim(($r->employee?->first_name ?? '').' '.($r->employee?->last_name ?? '')),
                optional($r->attendance_date)->toDateString(), $r->status, $r->total_worked_minutes, $r->total_break_minutes,
                $r->late_minutes, $r->early_leave_minutes, $r->overtime_minutes,
            ]);

        return [['employee_number', 'employee', 'date', 'status', 'worked_minutes', 'break_minutes', 'late_minutes', 'early_leave_minutes', 'overtime_minutes'], $rows];
    }
}
