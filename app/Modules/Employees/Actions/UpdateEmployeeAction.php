<?php

declare(strict_types=1);

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Models\Employee;
use Illuminate\Support\Facades\DB;

class UpdateEmployeeAction
{
    public function execute(Employee $employee, array $data, ?int $actorId): Employee
    {
        return DB::transaction(function () use ($employee, $data, $actorId) {
            $previousStatus = $employee->employment_status;
            $nextStatus = $data['employment_status'] ?? $previousStatus;
            $reason = $data['status_change_reason'] ?? null;
            unset($data['status_change_reason']);

            if (array_key_exists('employee_number', $data)) {
                $data['employee_number'] = trim((string) $data['employee_number']) ?: $employee->employee_number;
            }

            $employee->update($data);

            if ($nextStatus !== $previousStatus) {
                $employee->employmentHistories()->forceCreate([
                    'tenant_id' => $employee->tenant_id,
                    'previous_status' => $previousStatus,
                    'new_status' => $nextStatus,
                    'effective_date' => now()->toDateString(),
                    'reason' => $reason,
                    'changed_by_user_id' => $actorId,
                ]);
            }

            return $employee->refresh();
        }, 3);
    }
}
