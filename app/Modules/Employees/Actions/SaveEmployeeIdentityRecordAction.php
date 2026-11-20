<?php

declare(strict_types=1);

namespace App\Modules\Employees\Actions;

use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\EmployeeIdentityRecord;
use App\Modules\Employees\Services\IdentityNumberHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveEmployeeIdentityRecordAction
{
    public function __construct(
        private readonly IdentityNumberHasher $hasher
    ) {}

    public function execute(Employee $employee, array $data): EmployeeIdentityRecord
    {
        return DB::transaction(function () use ($employee, $data) {
            $tenantId = (string) $employee->tenant_id;

            // Serialize identity writes for this tenant so duplicate checks are race-safe.
            DB::table('tenants')
                ->where('id', $tenantId)
                ->lockForUpdate()
                ->first();

            $lockedEmployee = Employee::query()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($employee->id);

            $identityType = $data['identity_type'];
            $identityNumber = trim($data['identity_number']);
            $identityHash = $this->hasher->hash($identityType, $identityNumber);

            $existingRecord = EmployeeIdentityRecord::query()
                ->where('tenant_id', $tenantId)
                ->where('identity_type', $identityType)
                ->where('identity_number_hash', $identityHash)
                ->first();

            if ($existingRecord !== null && (int) $existingRecord->employee_id !== (int) $lockedEmployee->id) {
                throw ValidationException::withMessages([
                    'identity_number' => 'This identity number is already assigned within this tenant.',
                ]);
            }

            // Retain history: the previous current record is never overwritten when the number changes.
            $lockedEmployee->identityRecords()
                ->where('identity_type', $identityType)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $attributes = [
                'issuing_country' => $data['issuing_country'] ?? null,
                'issue_date' => $data['issue_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'is_current' => true,
            ];

            if ($existingRecord !== null) {
                $existingRecord->forceFill($attributes)->save();

                return $existingRecord->refresh();
            }

            return EmployeeIdentityRecord::query()->forceCreate([
                ...$attributes,
                'tenant_id' => $tenantId,
                'employee_id' => $lockedEmployee->id,
                'identity_type' => $identityType,
                'identity_number' => $identityNumber,
                'identity_number_hash' => $identityHash,
            ]);
        }, 3);
    }
}
