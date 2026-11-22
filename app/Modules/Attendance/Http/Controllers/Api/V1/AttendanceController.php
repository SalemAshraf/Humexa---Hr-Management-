<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSession;
use App\Modules\Attendance\Services\AttendanceMobileAction;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\File;

class AttendanceController extends Controller
{
    private function employee(Request $request): Employee
    {
        abort_unless(tenancy()->initialized, Response::HTTP_NOT_FOUND);

        $user = $request->user();
        abort_unless(
            $user && $user->account_type === 'tenant' && (string) $user->tenant_id === (string) tenant('id'),
            Response::HTTP_FORBIDDEN
        );

        return Employee::query()
            ->where('tenant_id', tenant('id'))
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    public function policy(Request $request)
    {
        $this->employee($request);
        $policy = AttendancePolicy::query()->where('tenant_id', tenant('id'))->firstOrFail();

        $company = \App\Modules\Settings\Models\CompanySetting::query()->where('tenant_id', tenant('id'))->first();

        return response()->json(['company' => [
            'configured' => $company !== null,
            'timezone' => $company?->timezone ?? 'Africa/Cairo',
            'work_start' => substr((string) ($company?->work_start ?? '09:00'), 0, 5),
            'work_end' => substr((string) ($company?->work_end ?? '17:00'), 0, 5),
        ], 'data' => [
            'mobile_checkin_enabled' => $policy->mobile_checkin_enabled,
            'location_verification_enabled' => $policy->location_verification_enabled,
            'selfie_verification_enabled' => $policy->selfie_verification_enabled,
            'fingerprint_device_enabled' => $policy->fingerprint_device_enabled,
            'attendance_correction_enabled' => $policy->attendance_correction_enabled,
            'overtime_requires_approval' => $policy->overtime_requires_approval,
            'location_accuracy_limit' => $policy->location_accuracy_limit,
            'allowed_location_radius' => $policy->allowed_location_radius,
        ]]);
    }

    public function status(Request $request)
    {
        $employee = $this->employee($request);
        $date = \App\Modules\Settings\Support\CompanyClock::today((string) tenant('id'));

        $openSession = AttendanceSession::query()
            ->where('tenant_id', tenant('id'))
            ->where('employee_id', $employee->id)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->with(['breaks', 'attendanceRecord.sessions.breaks'])
            ->latest('id')
            ->first();

        $record = $openSession?->attendanceRecord;

        if (! $record) {
            $record = AttendanceRecord::query()
                ->where('tenant_id', tenant('id'))
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $date)
                ->with(['sessions.breaks'])
                ->first();
        }

        return response()->json(['data' => [
            'date' => $date,
            'record' => $record,
            'active_session' => $openSession,
            'state' => $openSession ? 'checked_in' : ($record ? 'checked_out' : 'not_checked_in'),
        ]]);
    }

    public function checkIn(Request $request, AttendanceMobileAction $action)
    {
        $employee = $this->employee($request);
        $data = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|integer|min:0',
            'client_timestamp' => 'nullable|date',
            'selfie' => ['nullable', File::image()->max(5 * 1024)],
        ]);

        $session = $action->record($employee, 'check_in', $data, $request->file('selfie'));

        return response()->json(['message' => 'Check-in recorded.', 'data' => $session], 201);
    }

    public function checkOut(Request $request, AttendanceMobileAction $action)
    {
        $employee = $this->employee($request);
        $data = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|integer|min:0',
            'client_timestamp' => 'nullable|date',
            'selfie' => ['nullable', File::image()->max(5 * 1024)],
        ]);

        $session = $action->record($employee, 'check_out', $data, $request->file('selfie'));

        return response()->json(['message' => 'Check-out recorded.', 'data' => $session]);
    }

    public function history(Request $request)
    {
        $employee = $this->employee($request);
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AttendanceRecord::query()
            ->where('tenant_id', tenant('id'))
            ->where('employee_id', $employee->id)
            ->with(['sessions.breaks']);

        if (isset($validated['from'])) {
            $query->whereDate('attendance_date', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('attendance_date', '<=', $validated['to']);
        }

        return response()->json($query->orderByDesc('attendance_date')->paginate($validated['per_page'] ?? 20));
    }
}
