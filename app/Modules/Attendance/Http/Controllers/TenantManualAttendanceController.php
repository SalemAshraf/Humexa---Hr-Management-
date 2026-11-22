<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendanceEvent;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSession;
use App\Modules\Attendance\Services\AttendanceMobileAction;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** HR entry of a finished work session, and the two attendance switches HR may flip (attendance.manage). */
class TenantManualAttendanceController extends Controller
{
    public function store(Request $request, AttendanceMobileAction $action): RedirectResponse
    {
        abort_unless(tenancy()->initialized, 404);
        $tenantId = (string) tenant()->getTenantKey();
        $user = $request->user('tenant');
        abort_unless($user && hash_equals($tenantId, (string) $user->tenant_id), 403);

        $policy = AttendancePolicy::query()->where('tenant_id', $tenantId)->first();
        if (! ($policy?->manual_attendance_enabled)) {
            throw ValidationException::withMessages(['manual' => 'Manual attendance entry is turned off. Turn it on first.']);
        }

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'check_in' => ['required', 'date_format:H:i'],
            'check_out' => ['required', 'date_format:H:i', 'after:check_in'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = Employee::query()->where('tenant_id', $tenantId)->findOrFail($data['employee_id']);
        $in = Carbon::parse($data['date'].' '.$data['check_in']);
        $out = Carbon::parse($data['date'].' '.$data['check_out']);

        DB::transaction(function () use ($tenantId, $employee, $data, $in, $out, $user, $action) {
            $record = AttendanceRecord::query()
                ->where('tenant_id', $tenantId)->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $data['date'])->lockForUpdate()->first()
                ?? AttendanceRecord::create([
                    'tenant_id' => $tenantId,
                    'employee_id' => $employee->id,
                    'attendance_date' => $data['date'],
                    'status' => 'recorded',
                ]);

            $overlap = AttendanceSession::query()
                ->where('tenant_id', $tenantId)->where('attendance_record_id', $record->id)
                ->where('check_in_at', '<', $out)
                ->where(fn ($q) => $q->whereNull('check_out_at')->orWhere('check_out_at', '>', $in))
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['manual' => 'This overlaps an existing session on that day.']);
            }

            $session = AttendanceSession::create([
                'tenant_id' => $tenantId,
                'attendance_record_id' => $record->id,
                'employee_id' => $employee->id,
                'session_number' => ((int) AttendanceSession::query()->where('tenant_id', $tenantId)->where('attendance_record_id', $record->id)->max('session_number')) + 1,
                'check_in_at' => $in,
                'check_out_at' => $out,
                'check_in_source' => 'manual',
                'check_out_source' => 'manual',
                'break_minutes' => 0,
                'worked_minutes' => $in->diffInMinutes($out),
                'status' => 'completed',
            ]);

            foreach ([['check_in', $in], ['check_out', $out]] as [$type, $at]) {
                AttendanceEvent::create([
                    'tenant_id' => $tenantId,
                    'attendance_session_id' => $session->id,
                    'employee_id' => $employee->id,
                    'event_type' => $type,
                    'source' => 'manual',
                    'occurred_at' => $at,
                    'metadata' => ['entered_by_user_id' => $user->id, 'note' => $data['note'] ?? null],
                ]);
            }

            $action->refreshDailySummary($record);
        });

        return back()->with('success', 'Attendance entry saved.');
    }

    public function updatePolicy(Request $request): RedirectResponse
    {
        abort_unless(tenancy()->initialized, 404);
        $tenantId = (string) tenant()->getTenantKey();

        $data = $request->validate([
            'mobile_checkin_enabled' => ['required', 'boolean'],
            'manual_attendance_enabled' => ['required', 'boolean'],
        ]);

        AttendancePolicy::query()->where('tenant_id', $tenantId)->firstOrFail()->update($data);

        return back()->with('success', 'Attendance policy updated.');
    }
}
