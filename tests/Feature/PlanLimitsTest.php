<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Employees\Models\Employee;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Services\PlanLimitReached;
use App\Modules\Plans\Services\PlanLimits;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => []]);
    $this->tenant = app(RegisterCompanyAction::class)->execute(companyName: 'Plan Co', adminName: 'Owner', email: 'owner@plan-test.example', password: 'StrongPassword123!')['tenant'];
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

function planEmployee(string $tenantId, string $n): array
{
    return ['first_name' => 'A' . $n, 'last_name' => 'B', 'employment_status' => 'active', 'hire_date' => '2026-01-01'];
}

it('has no limits for a company without a plan', function () {
    $limits = app(PlanLimits::class);
    expect($limits->planFor((string) $this->tenant->getKey()))->toBeNull();
    $limits->assertCanAddEmployee((string) $this->tenant->getKey());
    $limits->assertCanAddAccount((string) $this->tenant->getKey());
    expect(true)->toBeTrue();
});

it('blocks only new employees at the limit and keeps existing data', function () {
    $id = (string) $this->tenant->getKey();
    $plan = Plan::query()->create(['name' => 'Tiny', 'max_employees' => 1, 'max_accounts' => 1, 'is_active' => true]);
    $before = Employee::query()->where('tenant_id', $id)->count();

    $this->tenant->setAttribute('plan_id', $plan->id);
    $this->tenant->save();

    $action = app(CreateEmployeeAction::class);
    $needed = max(0, 1 - $before);
    for ($i = 0; $i < $needed; $i++) {
        $action->execute(planEmployee($id, (string) $i), $id, null);
    }

    expect(fn () => $action->execute(planEmployee($id, 'x'), $id, null))->toThrow(PlanLimitReached::class);
    expect(Employee::query()->where('tenant_id', $id)->count())->toBe(max($before, 1));

    // Lowering a limit below current use never removes anything.
    $plan->update(['max_employees' => 1]);
    expect(Employee::query()->where('tenant_id', $id)->count())->toBe(max($before, 1));
});

it('blocks new sign-in accounts at the limit', function () {
    $id = (string) $this->tenant->getKey();
    $used = app(PlanLimits::class)->usage($id)['accounts'];
    $plan = Plan::query()->create(['name' => 'Tiny2', 'max_employees' => null, 'max_accounts' => $used, 'is_active' => true]);
    $this->tenant->setAttribute('plan_id', $plan->id);
    $this->tenant->save();

    expect(fn () => app(PlanLimits::class)->assertCanAddAccount($id))->toThrow(PlanLimitReached::class);
});
