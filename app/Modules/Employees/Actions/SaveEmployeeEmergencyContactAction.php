<?php

declare(strict_types=1);

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeEmergencyContact;
use Illuminate\Support\Facades\DB;

class SaveEmployeeEmergencyContactAction
{
    public function execute(
        Employee $employee,
        array $data,
        ?EmployeeEmergencyContact $contact = null
    ): EmployeeEmergencyContact {
        return DB::transaction(function () use ($employee, $data, $contact) {
            $lockedEmployee = Employee::query()
                ->where('tenant_id', $employee->tenant_id)
                ->lockForUpdate()
                ->findOrFail($employee->id);

            if ($contact !== null) {
                $contact = $lockedEmployee->emergencyContacts()
                    ->whereKey($contact->getKey())
                    ->firstOrFail();

                $contact->forceFill($data)->save();

                return $contact->refresh();
            }

            $nextPriority = ((int) $lockedEmployee->emergencyContacts()->max('priority')) + 1;

            return EmployeeEmergencyContact::query()->forceCreate([
                ...$data,
                'tenant_id' => $lockedEmployee->tenant_id,
                'employee_id' => $lockedEmployee->id,
                'priority' => $nextPriority,
            ]);
        }, 3);
    }
}
