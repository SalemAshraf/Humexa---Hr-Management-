<?php

declare(strict_types=1);

namespace App\Modules\Employees\Policies;

use App\Models\User;
use App\Modules\Employees\Models\Employee;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isCurrentTenantUser($user);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->ownsEmployee($user, $employee);
    }

    public function create(User $user): bool
    {
        return $this->isCurrentTenantUser($user);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->ownsEmployee($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->ownsEmployee($user, $employee);
    }

    private function isCurrentTenantUser(User $user): bool
    {
        return tenancy()->initialized
            && $user->account_type === User::ACCOUNT_TYPE_TENANT
            && $user->tenant_id !== null
            && (string) $user->tenant_id === (string) tenant()->getTenantKey();
    }

    private function ownsEmployee(User $user, Employee $employee): bool
    {
        return $this->isCurrentTenantUser($user)
            && (string) $employee->tenant_id === (string) tenant()->getTenantKey();
    }
}
