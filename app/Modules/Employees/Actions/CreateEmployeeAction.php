<?php

declare(strict_types=1);

namespace App\Modules\Employees\Actions;

use App\Models\Tenant;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeEmploymentHistory;
use Illuminate\Support\Facades\DB;

class CreateEmployeeAction
{
    public function execute(array $data, string $tenantId, ?int $actorId): Employee
    {
        return DB::transaction(function () use ($data, $tenantId, $actorId) {
            unset($data['status_change_reason']);

            $employeeNumber = trim((string) ($data['employee_number'] ?? ''));

            if ($employeeNumber === '') {
                // Serialize automatic number generation per tenant.
                Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

                $sequence = Employee::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->count() + 1;

                do {
                    $employeeNumber = 'EMP-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                    $sequence++;
                } while (Employee::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('employee_number', $employeeNumber)
                    ->exists());
            }

            $employee = Employee::query()->forceCreate([
                ...$data,
                'employee_number' => $employeeNumber,
                'tenant_id' => $tenantId,
            ]);

            EmployeeEmploymentHistory::query()->forceCreate([
                'employee_id' => $employee->id,
                'tenant_id' => $tenantId,
                'previous_status' => null,
                'new_status' => $employee->employment_status,
                'effective_date' => $employee->hire_date?->toDateString() ?? now()->toDateString(),
                'reason' => 'Employee record created.',
                'changed_by_user_id' => $actorId,
            ]);

            return $employee;
        }, 3);
    }
}
