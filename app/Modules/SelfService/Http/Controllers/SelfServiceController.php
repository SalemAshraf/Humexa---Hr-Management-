<?php

declare(strict_types=1);

namespace App\Modules\SelfService\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSession;
use App\Modules\Attendance\Services\AttendanceMobileAction;
use App\Modules\Employees\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Services\LeaveService;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My space": a signed-in person sees only the employee record linked to their
 * own account. Every query is keyed on that one employee id, never on input.
 */
class SelfServiceController extends Controller
{
    public function __construct(private readonly LeaveService $leave)
    {
    }

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $user = $this->user($request);
        $employee = $this->employee($tenantId, $user);

        if (! $employee) {
            return Inertia::render('Company/Me/Index', ['linked' => false]);
        }

        $year = (int) now()->year;

        $requests = LeaveRequest::query()
            ->where('tenant_id', $tenantId)->where('employee_id', $employee->id)
            ->with('type:id,tenant_id,name')->latest('id')->limit(30)->get()
            ->map(fn (LeaveRequest $r) => [
                'id' => $r->id,
                'type' => $r->type?->name,
                'start_date' => $r->start_date->toDateString(),
                'end_date' => $r->end_date->toDateString(),
                'days' => $r->days,
                'status' => $r->status,
                'reason' => $r->reason,
                'review_note' => $r->review_note,
                'can_cancel' => $r->status === LeaveRequest::PENDING,
            ]);

        $attendance = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)->where('employee_id', $employee->id)
            ->orderByDesc('attendance_date')->limit(30)->get()
            ->map(fn (AttendanceRecord $a) => [
                'date' => $a->attendance_date->toDateString(),
                'status' => $a->status,
                'worked_minutes' => $a->total_worked_minutes,
                'late_minutes' => $a->late_minutes,
                'overtime_minutes' => $a->overtime_minutes,
            ]);

        // Only runs HR has locked, and only this person's own line.
        $payslips = Payslip::query()
            ->where('tenant_id', $tenantId)->where('employee_id', $employee->id)
            ->whereHas('run', fn ($q) => $q->where('status', PayrollRun::APPROVED))
            ->with('run:id,year,month')->latest('id')->limit(12)->get()
            ->map(fn (Payslip $p) => [
                'id' => $p->id,
                'period' => sprintf('%04d-%02d', $p->run->year, $p->run->month),
                'gross' => $p->gross,
                'insurance_employee' => $p->insurance_employee,
                'income_tax' => $p->income_tax,
                'net_pay' => $p->net_pay,
            ]);

        return Inertia::render('Company/Me/Index', [
            'linked' => true,
            'profile' => [
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'hire_date' => $employee->hire_date?->toDateString(),
                'status' => $employee->employment_status,
                'email' => $user->email,
            ],
            'balances' => $this->leave->balances($tenantId, $employee->id, $year),
            'types' => LeaveType::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'requests' => $requests,
            'attendance' => $attendance,
            'clock' => $this->clockState($tenantId, $employee),
            'payslips' => $payslips,
            'year' => $year,
        ]);
    }

    /** Today's check-in state and the policy flags the clock card needs. */
    private function clockState(string $tenantId, Employee $employee): array
    {
        $policy = AttendancePolicy::query()->where('tenant_id', $tenantId)->first();
        $open = AttendanceSession::query()
            ->where('tenant_id', $tenantId)->where('employee_id', $employee->id)
            ->whereNotNull('check_in_at')->whereNull('check_out_at')
            ->latest('id')->first();

        return [
            'enabled' => (bool) ($policy?->mobile_checkin_enabled ?? false),
            'needs_location' => (bool) ($policy?->location_verification_enabled ?? false),
            'needs_selfie' => (bool) ($policy?->selfie_verification_enabled ?? false),
            'state' => $open ? 'checked_in' : 'not_checked_in',
            'since' => $open?->check_in_at?->toIso8601String(),
        ];
    }

    public function checkIn(Request $request, AttendanceMobileAction $action): RedirectResponse
    {
        return $this->clock($request, $action, 'check_in', 'Checked in.');
    }

    public function checkOut(Request $request, AttendanceMobileAction $action): RedirectResponse
    {
        return $this->clock($request, $action, 'check_out', 'Checked out.');
    }

    private function clock(Request $request, AttendanceMobileAction $action, string $type, string $message): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $employee = $this->employee($tenantId, $this->user($request));
        abort_unless($employee, 403, 'No employee profile is linked to this account.');

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0'],
            'selfie' => ['nullable', File::image()->max(5 * 1024)],
        ]);

        $action->record($employee, $type, $data, $request->file('selfie'), 'web');

        return back()->with('success', $message);
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $user = $this->user($request);
        $employee = $this->employee($tenantId, $user);
        abort_unless($employee, 403, 'No employee profile is linked to this account.');

        $data = $request->validate([
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = LeaveType::query()->where('tenant_id', $tenantId)->findOrFail($data['leave_type_id']);

        $this->leave->submit($tenantId, $employee, $type, Carbon::parse($data['start_date']), Carbon::parse($data['end_date']), $data['reason'] ?? null, $user);

        return back()->with('success', 'Leave request sent for approval.');
    }

    public function cancelLeave(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $user = $this->user($request);
        $employee = $this->employee($tenantId, $user);
        abort_unless($employee, 403);

        $model = LeaveRequest::query()
            ->where('tenant_id', $tenantId)->where('employee_id', $employee->id)
            ->findOrFail((int) $request->route('leaveRequest'));
        abort_unless($model->status === LeaveRequest::PENDING, 422, 'Only a pending request can be withdrawn.');

        $model->forceFill(['status' => LeaveRequest::CANCELLED, 'review_note' => 'Withdrawn by the employee.'])->save();

        return back()->with('success', 'Request withdrawn.');
    }

    private function employee(string $tenantId, User $user): ?Employee
    {
        return Employee::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->with(['department', 'position'])->first();
    }

    private function tenantId(): string
    {
        abort_unless(tenancy()->initialized, 404);

        return (string) tenant()->getTenantKey();
    }

    private function user(Request $request): User
    {
        $user = $request->user('tenant');
        abort_unless($user instanceof User && hash_equals($this->tenantId(), (string) $user->tenant_id), 403);

        return $user;
    }
}
