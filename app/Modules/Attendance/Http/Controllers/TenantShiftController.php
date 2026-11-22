<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendanceShift;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Work shifts (attendance.manage). Assigning is always explicit: nobody gets a shift automatically. */
class TenantShiftController extends Controller
{
    private function tenantId(Request $request): string
    {
        abort_unless(tenancy()->initialized, 404);
        $id = (string) tenant()->getTenantKey();
        $user = $request->user('tenant');
        abort_unless($user && hash_equals($id, (string) $user->tenant_id), 403);

        return $id;
    }

    public function index(Request $request): Response
    {
        $t = $this->tenantId($request);
        $counts = Employee::query()->where('tenant_id', $t)->whereNotNull('shift_id')->selectRaw('shift_id, count(*) c')->groupBy('shift_id')->pluck('c', 'shift_id');

        return Inertia::render('Company/Attendance/Shifts', [
            'shifts' => AttendanceShift::query()->where('tenant_id', $t)->orderBy('name')->get()->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name,
                'start_time' => substr((string) $s->start_time, 0, 5), 'end_time' => substr((string) $s->end_time, 0, 5),
                'grace_minutes' => $s->grace_minutes, 'employees' => (int) ($counts[$s->id] ?? 0),
            ]),
            'employees' => Employee::query()->where('tenant_id', $t)->orderBy('first_name')->limit(500)->get(['id', 'first_name', 'last_name', 'shift_id'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => trim($e->first_name.' '.$e->last_name), 'shift_id' => $e->shift_id]),
        ]);
    }

    private function rules(string $t, ?int $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('attendance_shifts', 'name')->where('tenant_id', $t)->ignore($ignore)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $t = $this->tenantId($request);
        AttendanceShift::create($request->validate($this->rules($t)) + ['tenant_id' => $t]);

        return back();
    }

    public function update(Request $request): RedirectResponse
    {
        $t = $this->tenantId($request);
        $shift = (int) $request->route('shift');
        $model = AttendanceShift::query()->where('tenant_id', $t)->findOrFail($shift);
        $model->update($request->validate($this->rules($t, $model->id)));

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $t = $this->tenantId($request);
        $shift = (int) $request->route('shift');
        $model = AttendanceShift::query()->where('tenant_id', $t)->findOrFail($shift);
        if (Employee::query()->where('tenant_id', $t)->where('shift_id', $model->id)->exists()) {
            throw ValidationException::withMessages(['shift' => 'Move the employees on this shift to another shift or none before deleting it.']);
        }
        $model->delete();

        return back();
    }

    public function assign(Request $request): RedirectResponse
    {
        $t = $this->tenantId($request);
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'shift_id' => ['nullable', 'integer']]);
        $employee = Employee::query()->where('tenant_id', $t)->findOrFail($data['employee_id']);
        if ($data['shift_id'] !== null) {
            AttendanceShift::query()->where('tenant_id', $t)->findOrFail($data['shift_id']);
        }
        $employee->forceFill(['shift_id' => $data['shift_id']])->save();

        return back();
    }
}
