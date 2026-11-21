<?php

declare(strict_types=1);

namespace App\Modules\Leave\Services;

use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    /**
     * The company's weekend (Carbon day numbers, 0 = Sunday). Falls back to the
     * config default until the company saves its own setting.
     *
     * @return list<int>
     */
    public function weekendDays(string $tenantId): array
    {
        $saved = LeaveSetting::query()->where('tenant_id', $tenantId)->value('weekend_days');

        return array_map('intval', is_array($saved) ? $saved : config('humexa.leave.weekend_days', [5, 6]));
    }

    /**
     * Working days between two dates, inclusive.
     *
     * @param list<int>|null $weekend
     */
    public function workingDays(Carbon $start, Carbon $end, ?array $weekend = null): float
    {
        $weekend ??= config('humexa.leave.weekend_days', [5, 6]);
        $days = 0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if (! in_array($d->dayOfWeek, $weekend, true)) {
                $days++;
            }
        }

        return (float) $days;
    }

    public function usedDays(string $tenantId, int $employeeId, int $leaveTypeId, int $year): float
    {
        return (float) LeaveRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('status', LeaveRequest::APPROVED)
            ->whereYear('start_date', $year)
            ->sum('days');
    }

    /**
     * @return list<array{leave_type_id:int,name:string,entitled:float,used:float,pending:float,remaining:float}>
     */
    public function balances(string $tenantId, int $employeeId, int $year): array
    {
        return LeaveType::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (LeaveType $type) use ($tenantId, $employeeId, $year) {
                $used = $this->usedDays($tenantId, $employeeId, $type->id, $year);
                $pending = (float) LeaveRequest::query()
                    ->where('tenant_id', $tenantId)
                    ->where('employee_id', $employeeId)
                    ->where('leave_type_id', $type->id)
                    ->where('status', LeaveRequest::PENDING)
                    ->whereYear('start_date', $year)
                    ->sum('days');

                return [
                    'leave_type_id' => $type->id,
                    'name' => $type->name,
                    'entitled' => $type->annual_days,
                    'used' => $used,
                    'pending' => $pending,
                    'remaining' => $type->annual_days - $used - $pending,
                ];
            })
            ->all();
    }

    public function submit(string $tenantId, Employee $employee, LeaveType $type, Carbon $start, Carbon $end, ?string $reason, ?User $by): LeaveRequest
    {
        if ($employee->tenant_id !== $tenantId || $type->tenant_id !== $tenantId) {
            abort(404);
        }

        if (! $type->is_active) {
            throw ValidationException::withMessages(['leave_type_id' => 'This leave type is not active.']);
        }

        if (in_array($employee->employment_status, [Employee::STATUS_RESIGNED, Employee::STATUS_TERMINATED], true)) {
            throw ValidationException::withMessages(['employee_id' => 'This employee has left the company.']);
        }

        if ($start->year !== $end->year) {
            throw ValidationException::withMessages(['end_date' => 'Split requests that cross a calendar year.']);
        }

        $days = $this->workingDays($start, $end, $this->weekendDays($tenantId));

        if ($days <= 0) {
            throw ValidationException::withMessages(['start_date' => 'The selected dates contain no working days.']);
        }

        return DB::transaction(function () use ($tenantId, $employee, $type, $start, $end, $days, $reason, $by) {
            // Lock the employee row so two simultaneous requests cannot both pass the checks.
            Employee::query()->where('tenant_id', $tenantId)->whereKey($employee->id)->lockForUpdate()->first();

            $overlap = LeaveRequest::query()
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->whereIn('status', [LeaveRequest::PENDING, LeaveRequest::APPROVED])
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages(['start_date' => 'These dates overlap another pending or approved leave request.']);
            }

            if ($type->annual_days > 0) {
                $balance = collect($this->balances($tenantId, $employee->id, $start->year))
                    ->firstWhere('leave_type_id', $type->id);

                if ($balance && $days > $balance['remaining']) {
                    throw ValidationException::withMessages([
                        'end_date' => "Only {$balance['remaining']} day(s) of {$type->name} remain for {$start->year}.",
                    ]);
                }
            }

            return LeaveRequest::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'reason' => $reason,
                'status' => LeaveRequest::PENDING,
                'submitted_by_user_id' => $by?->id,
            ]);
        });
    }

    public function review(LeaveRequest $request, User $reviewer, bool $approve, ?string $note): LeaveRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $approve, $note) {
            $fresh = LeaveRequest::query()
                ->where('tenant_id', $request->tenant_id)
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($fresh->status !== LeaveRequest::PENDING) {
                throw ValidationException::withMessages(['status' => 'This request was already reviewed.']);
            }

            $fresh->forceFill([
                'status' => $approve ? LeaveRequest::APPROVED : LeaveRequest::REJECTED,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            return $fresh;
        });
    }
}
