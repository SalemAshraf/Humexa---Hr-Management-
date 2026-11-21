<?php

declare(strict_types=1);

namespace App\Modules\Plans\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Plans\Models\Plan;
use Illuminate\Http\RedirectResponse;

/**
 * Plan limits only gate NEW additions. Existing data is never hidden, archived or deleted when a
 * company is over a limit, and a company without a plan has no limits.
 */
class PlanLimits
{
    public function planFor(string $tenantId): ?Plan
    {
        $planId = Tenant::query()->whereKey($tenantId)->value('plan_id');

        return $planId ? Plan::query()->find($planId) : null;
    }

    public function usage(string $tenantId): array
    {
        $plan = $this->planFor($tenantId);

        return [
            'plan' => $plan ? [
                'name' => $plan->name,
                'max_employees' => $plan->max_employees,
                'max_accounts' => $plan->max_accounts,
            ] : null,
            'employees' => Employee::query()->where('tenant_id', $tenantId)->count(),
            'accounts' => User::query()->where('tenant_id', $tenantId)->where('account_type', User::ACCOUNT_TYPE_TENANT)->count(),
        ];
    }

    public function assertCanAddEmployee(string $tenantId): void
    {
        $u = $this->usage($tenantId);
        $max = $u['plan']['max_employees'] ?? null;

        if ($max !== null && $u['employees'] >= $max) {
            throw new PlanLimitReached(__('Your plan allows :max employees and you have :used. Ask the platform team to change your plan before adding more. Existing employees are not affected.', ['max' => $max, 'used' => $u['employees']]));
        }
    }

    public function assertCanAddAccount(string $tenantId): void
    {
        $u = $this->usage($tenantId);
        $max = $u['plan']['max_accounts'] ?? null;

        if ($max !== null && $u['accounts'] >= $max) {
            throw new PlanLimitReached(__('Your plan allows :max sign-in accounts and you have :used. Ask the platform team to change your plan before adding more. Existing accounts are not affected.', ['max' => $max, 'used' => $u['accounts']]));
        }
    }
}
