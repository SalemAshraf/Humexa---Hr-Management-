<?php

declare(strict_types=1);

namespace App\Modules\Employees\Support;

use App\Models\User;
use App\Modules\Employees\Models\Employee;

/**
 * Who may see which employee records (owner decision: a manager sees their direct team only,
 * HR sees everyone). Anyone holding employees.manage sees all; others see their own
 * record and the people whose manager_id is their own employee record.
 */
final class EmployeeVisibility
{
    /** @return list<int>|null null means every employee of the company */
    public static function ids(string $tenantId, User $user): ?array
    {
        if ($user->can('employees.manage')) {
            return null;
        }

        $own = Employee::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->value('id');
        if ($own === null) {
            return [];
        }

        $reports = Employee::query()->where('tenant_id', $tenantId)->where('manager_id', $own)->pluck('id')->all();

        return array_values(array_unique(array_map('intval', [...$reports, $own])));
    }

    public static function canSee(string $tenantId, User $user, Employee $employee): bool
    {
        $ids = self::ids($tenantId, $user);

        return $ids === null || in_array((int) $employee->id, $ids, true);
    }
}
