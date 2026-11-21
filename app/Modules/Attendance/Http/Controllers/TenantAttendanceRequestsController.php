<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Attendance\Models\AttendanceCorrection;
use App\Modules\Attendance\Models\OvertimeRequest;
use App\Modules\Attendance\Services\AttendanceRequestReviewer;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** HR review queue for attendance corrections and overtime (attendance.manage). */
class TenantAttendanceRequestsController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $this->actor($request);

        $names = fn (array $ids) => Employee::query()->where('tenant_id', $tenantId)->whereIn('id', $ids)->get()->keyBy('id');

        $corrections = AttendanceCorrection::query()->where('tenant_id', $tenantId)
            ->orderByRaw("status = 'pending' desc")->latest('id')->limit(50)->get();
        $overtime = OvertimeRequest::query()->where('tenant_id', $tenantId)
            ->orderByRaw("status = 'pending' desc")->latest('id')->limit(50)->get();
        $emps = $names($corrections->pluck('employee_id')->merge($overtime->pluck('employee_id'))->unique()->all());

        return Inertia::render('Company/Attendance/Requests', [
            'corrections' => $corrections->map(fn ($c) => [
                'id' => $c->id,
                'employee' => $emps[$c->employee_id]?->full_name,
                'requested_check_in' => $c->requested_check_in?->format('Y-m-d H:i'),
                'requested_check_out' => $c->requested_check_out?->format('Y-m-d H:i'),
                'reason' => $c->reason,
                'status' => $c->status,
                'review_note' => $c->review_note,
            ]),
            'overtime' => $overtime->map(fn ($o) => [
                'id' => $o->id,
                'employee' => $emps[$o->employee_id]?->full_name,
                'minutes' => $o->requested_minutes,
                'reason' => $o->reason,
                'status' => $o->status,
                'review_note' => $o->review_note,
            ]),
        ]);
    }

    public function reviewCorrection(Request $request, AttendanceRequestReviewer $reviewer): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $user = $this->actor($request);
        $model = AttendanceCorrection::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('correction'));
        $data = $this->decision($request);

        $reviewer->reviewCorrection($model, $user, $data['decision'] === 'approve', $data['note'] ?? null);

        return back()->with('success', 'Correction ' . ($data['decision'] === 'approve' ? 'approved and applied.' : 'rejected.'));
    }

    public function reviewOvertime(Request $request, AttendanceRequestReviewer $reviewer): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $user = $this->actor($request);
        $model = OvertimeRequest::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->route('overtime'));
        $data = $this->decision($request);

        $reviewer->reviewOvertime($model, $user, $data['decision'] === 'approve', $data['note'] ?? null);

        return back()->with('success', 'Overtime ' . ($data['decision'] === 'approve' ? 'approved.' : 'rejected.'));
    }

    private function decision(Request $request): array
    {
        return $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function tenantId(): string
    {
        abort_unless(tenancy()->initialized, 404);

        return (string) tenant()->getTenantKey();
    }

    private function actor(Request $request): User
    {
        $user = $request->user('tenant');
        abort_unless($user instanceof User && hash_equals($this->tenantId(), (string) $user->tenant_id) && $user->can('attendance.manage'), 403);

        return $user;
    }
}
