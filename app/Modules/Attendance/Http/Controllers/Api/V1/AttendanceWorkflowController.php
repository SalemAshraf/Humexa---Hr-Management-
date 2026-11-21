<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendanceBreak;
use App\Modules\Attendance\Models\AttendanceCorrection;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSession;
use App\Modules\Attendance\Models\OvertimeRequest;
use App\Modules\Attendance\Services\AttendanceMobileAction;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceWorkflowController extends Controller
{
    private function employee(Request $request): Employee
    {
        abort_unless(tenancy()->initialized, 404);
        $user = $request->user();
        abort_unless($user && $user->account_type === 'tenant' && (string) $user->tenant_id === (string) tenant('id'), 403);

        return Employee::query()
            ->where('tenant_id', tenant('id'))
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    private function session(Employee $employee, int $id): AttendanceSession
    {
        return AttendanceSession::query()
            ->where('tenant_id', tenant('id'))
            ->where('employee_id', $employee->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    public function startBreak(Request $request, AttendanceMobileAction $action)
    {
        $employee = $this->employee($request);
        $data = $request->validate(['attendance_session_id' => 'required|integer']);

        $break = DB::transaction(function () use ($employee, $data) {
            $session = AttendanceSession::query()
                ->where('tenant_id', tenant('id'))
                ->where('employee_id', $employee->id)
                ->whereKey($data['attendance_session_id'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($session->check_in_at && ! $session->check_out_at, 422, 'Breaks require an active attendance session.');

            if ($session->breaks()->whereNull('ended_at')->exists()) {
                throw ValidationException::withMessages(['break' => 'A break is already active.']);
            }

            return AttendanceBreak::create([
                'tenant_id' => tenant('id'),
                'attendance_session_id' => $session->id,
                'started_at' => now(),
            ]);
        }, 3);

        return response()->json(['data' => $break], 201);
    }

    public function endBreak(Request $request, AttendanceMobileAction $action)
    {
        $employee = $this->employee($request);
        $data = $request->validate(['attendance_session_id' => 'required|integer']);

        [$break, $record] = DB::transaction(function () use ($employee, $data) {
            $session = AttendanceSession::query()
                ->where('tenant_id', tenant('id'))
                ->where('employee_id', $employee->id)
                ->whereKey($data['attendance_session_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $break = $session->breaks()
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->latest('id')
                ->firstOrFail();

            $break->ended_at = now();
            $break->save();

            $session->break_minutes = (int) $session->breaks()
                ->whereNotNull('ended_at')
                ->get()
                ->sum(fn ($item) => max(0, $item->started_at->diffInMinutes($item->ended_at)));
            $session->save();

            return [$break, $session->attendanceRecord];
        }, 3);

        $action->refreshDailySummary($record);

        return response()->json(['data' => $break]);
    }

    public function requestCorrection(Request $request)
    {
        $employee = $this->employee($request);
        abort_unless(
            AttendancePolicy::query()->where('tenant_id', tenant('id'))->value('attendance_correction_enabled'),
            403,
            'Attendance corrections are disabled by company policy.'
        );

        $data = $request->validate([
            'attendance_session_id' => 'required|integer',
            'requested_check_in' => 'nullable|date',
            'requested_check_out' => 'nullable|date|after_or_equal:requested_check_in',
            'reason' => 'required|string|max:2000',
        ]);

        $session = $this->session($employee, $data['attendance_session_id']);

        $correction = AttendanceCorrection::create([
            'tenant_id' => tenant('id'),
            'attendance_session_id' => $session->id,
            'employee_id' => $employee->id,
            'requested_by_user_id' => $request->user()->id,
            'requested_check_in' => $data['requested_check_in'] ?? null,
            'requested_check_out' => $data['requested_check_out'] ?? null,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        return response()->json(['data' => $correction], 201);
    }

    public function corrections(Request $request)
    {
        $employee = $this->employee($request);

        return response()->json(['data' => AttendanceCorrection::query()
            ->where('tenant_id', tenant('id'))
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate(20)]);
    }

    public function requestOvertime(Request $request)
    {
        $employee = $this->employee($request);
        $data = $request->validate([
            'attendance_session_id' => 'required|integer',
            'requested_minutes' => 'required|integer|min:1|max:1440',
            'reason' => 'nullable|string|max:2000',
        ]);

        $session = $this->session($employee, $data['attendance_session_id']);
        abort_unless($session->check_in_at && $session->check_out_at, 422, 'Overtime requires a completed attendance session.');

        $requestRecord = OvertimeRequest::create([
            'tenant_id' => tenant('id'),
            'attendance_session_id' => $session->id,
            'employee_id' => $employee->id,
            'requested_minutes' => $data['requested_minutes'],
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json(['data' => $requestRecord], 201);
    }

    public function overtimeRequests(Request $request)
    {
        $employee = $this->employee($request);

        return response()->json(['data' => OvertimeRequest::query()
            ->where('tenant_id', tenant('id'))
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate(20)]);
    }
}
