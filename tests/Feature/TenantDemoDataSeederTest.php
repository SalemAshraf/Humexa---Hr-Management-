<?php

declare(strict_types=1);

use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
        'tenancy.central_domains' => ['humexa.test'],
    ]);
});

function registerDemoSeedTenant(string $company, string $email): array
{
    return app(RegisterCompanyAction::class)->execute(
        companyName: $company,
        adminName: $company . ' Admin',
        email: $email,
        password: 'StrongPassword123!',
    );
}

test('demo command seeds organization and employees for only the selected tenant and is idempotent', function () {
    $target = registerDemoSeedTenant('Demo Seed Target', 'demo-seed-target@example.test');
    $other = registerDemoSeedTenant('Demo Seed Other', 'demo-seed-other@example.test');

    $this->artisan('humexa:seed-demo', ['tenant' => $target['tenant']->id])
        ->assertExitCode(0);

    $this->artisan('humexa:seed-demo', ['tenant' => $target['tenant']->id])
        ->assertExitCode(0);

    expect(DB::table('branches')->where('tenant_id', $target['tenant']->id)->count())->toBe(3)
        ->and(DB::table('departments')->where('tenant_id', $target['tenant']->id)->count())->toBe(10)
        ->and(DB::table('positions')->where('tenant_id', $target['tenant']->id)->count())->toBe(16)
        ->and(DB::table('employees')->where('tenant_id', $target['tenant']->id)->count())->toBe(12)
        ->and(DB::table('employee_employment_histories')->where('tenant_id', $target['tenant']->id)->count())->toBe(12);

    expect(DB::table('branches')->where('tenant_id', $other['tenant']->id)->count())->toBe(0)
        ->and(DB::table('departments')->where('tenant_id', $other['tenant']->id)->count())->toBe(0)
        ->and(DB::table('positions')->where('tenant_id', $other['tenant']->id)->count())->toBe(0)
        ->and(DB::table('employees')->where('tenant_id', $other['tenant']->id)->count())->toBe(0);
});
