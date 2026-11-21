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


test('module demo command needs confirm, targets one tenant, is repeatable and touches only demo employees', function () {
    $target = registerDemoSeedTenant('Mod Demo Target', 'mod-demo-target@example.test');
    $other = registerDemoSeedTenant('Mod Demo Other', 'mod-demo-other@example.test');
    $tid = $target['tenant']->id;

    $this->artisan('humexa:seed-demo-modules', ['tenant' => $tid, '--confirm' => true])->assertExitCode(1); // no demo org yet
    $this->artisan('humexa:seed-demo', ['tenant' => $tid])->assertExitCode(0);
    $this->artisan('humexa:seed-demo-modules', ['tenant' => $tid])->assertExitCode(1); // no --confirm
    expect(DB::table('job_openings')->where('tenant_id', $tid)->count())->toBe(0);

    $this->artisan('humexa:seed-demo-modules', ['tenant' => $tid, '--confirm' => true])->assertExitCode(0);
    $count = fn (string $t) => [DB::table('job_openings')->where('tenant_id', $t)->count(), DB::table('candidates')->where('tenant_id', $t)->count(), DB::table('performance_reviews')->where('tenant_id', $t)->count(), DB::table('leave_requests')->where('tenant_id', $t)->count(), DB::table('employee_documents')->where('tenant_id', $t)->count(), DB::table('employee_compensations')->where('tenant_id', $t)->count()];
    $first = $count($tid);
    expect($first[0])->toBe(3)->and($first[1])->toBe(6)->and($first[2])->toBe(3)->and($first[3])->toBe(1)->and($first[4])->toBe(3)->and($first[5])->toBeGreaterThan(5);

    $this->artisan('humexa:seed-demo-modules', ['tenant' => $tid, '--confirm' => true])->assertExitCode(0);
    expect($count($tid))->toBe($first)->and($count($other['tenant']->id))->toBe([0, 0, 0, 0, 0, 0]);
});
