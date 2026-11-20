<?php

declare(strict_types=1);

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeAddress;
use Illuminate\Support\Facades\DB;

class SaveEmployeeAddressAction
{
    public function execute(
        Employee $employee,
        array $data,
        ?EmployeeAddress $address = null
    ): EmployeeAddress {
        return DB::transaction(function () use ($employee, $data, $address) {
            $lockedEmployee = Employee::query()
                ->where('tenant_id', $employee->tenant_id)
                ->lockForUpdate()
                ->findOrFail($employee->id);

            if ($address !== null) {
                $address = $lockedEmployee->addresses()
                    ->whereKey($address->getKey())
                    ->firstOrFail();
            }

            if ($data['is_primary']) {
                EmployeeAddress::query()
                    ->where('tenant_id', $lockedEmployee->tenant_id)
                    ->where('employee_id', $lockedEmployee->id)
                    ->when($address, fn ($query) => $query->where('id', '!=', $address->getKey()))
                    ->update(['is_primary' => false]);
            }

            if ($address === null) {
                return EmployeeAddress::query()->forceCreate([
                    ...$data,
                    'tenant_id' => $lockedEmployee->tenant_id,
                    'employee_id' => $lockedEmployee->id,
                ]);
            }

            $address->forceFill($data)->save();

            return $address->refresh();
        }, 3);
    }
}
