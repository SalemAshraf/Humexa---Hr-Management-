<?php

declare(strict_types=1);

use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
        'tenancy.central_domains' => ['humexa.test'],
    ]);
});

function registerOrganizationTenant(string $company, string $email): array
{
    return app(RegisterCompanyAction::class)->execute(
        companyName: $company,
        adminName: $company . ' Admin',
        email: $email,
        password: 'StrongPassword123!',
    );
}

test('tenant administrator can manage branches departments and positions', function () {
    $registration = registerOrganizationTenant('Organization Alpha', 'org-alpha@example.test');

    $this->actingAs($registration['user'], 'tenant')
        ->withSession(['tenant_session_version' => 1]);

    $base = 'http://' . $registration['domain'] . '/organization';

    $this->get($base)->assertOk()->assertInertia(fn ($page) => $page
        ->component('Organization/Index')
        ->where('summary.branches', 0)
        ->where('summary.departments', 0)
        ->where('summary.positions', 0)
    );

    $this->post($base . '/branches', [
        'name' => 'Alexandria HQ',
        'code' => 'ALX-01',
        'city' => 'Alexandria',
        'is_active' => true,
    ])->assertRedirect();

    $branchId = DB::table('branches')->where('tenant_id', $registration['tenant']->id)->value('id');

    $this->post($base . '/departments', [
        'branch_id' => $branchId,
        'name' => 'Human Resources',
        'code' => 'HR',
        'is_active' => true,
    ])->assertRedirect();

    $departmentId = DB::table('departments')->where('tenant_id', $registration['tenant']->id)->value('id');

    $this->post($base . '/positions', [
        'department_id' => $departmentId,
        'name' => 'HR Specialist',
        'code' => 'HR-SPEC',
        'is_active' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('branches', [
        'id' => $branchId,
        'tenant_id' => $registration['tenant']->id,
        'name' => 'Alexandria HQ',
    ]);
    $this->assertDatabaseHas('departments', [
        'id' => $departmentId,
        'tenant_id' => $registration['tenant']->id,
        'branch_id' => $branchId,
        'name' => 'Human Resources',
    ]);
    $this->assertDatabaseHas('positions', [
        'tenant_id' => $registration['tenant']->id,
        'department_id' => $departmentId,
        'name' => 'HR Specialist',
    ]);

    $this->get($base)->assertOk()->assertInertia(fn ($page) => $page
        ->where('summary.branches', 1)
        ->where('summary.departments', 1)
        ->where('summary.positions', 1)
    );
});

test('tenant cannot attach an organization record to another tenant branch or department', function () {
    $first = registerOrganizationTenant('Organization First', 'org-first@example.test');
    $second = registerOrganizationTenant('Organization Second', 'org-second@example.test');

    $foreignBranchId = DB::table('branches')->insertGetId([
        'tenant_id' => $first['tenant']->id,
        'name' => 'Foreign Branch',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $foreignDepartmentId = DB::table('departments')->insertGetId([
        'tenant_id' => $first['tenant']->id,
        'name' => 'Foreign Department',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($second['user'], 'tenant')
        ->withSession(['tenant_session_version' => 1]);

    $base = 'http://' . $second['domain'] . '/organization';

    $this->post($base . '/departments', [
        'branch_id' => $foreignBranchId,
        'name' => 'Attempted Cross Tenant Department',
    ])->assertSessionHasErrors('branch_id');

    $this->post($base . '/positions', [
        'department_id' => $foreignDepartmentId,
        'name' => 'Attempted Cross Tenant Position',
    ])->assertSessionHasErrors('department_id');

    $this->put($base . '/branches/' . $foreignBranchId, [
        'name' => 'Tampered Foreign Branch',
    ])->assertNotFound();

    $this->assertDatabaseMissing('branches', [
        'id' => $foreignBranchId,
        'name' => 'Tampered Foreign Branch',
    ]);
});

test('organization parent records cannot be archived while child records exist', function () {
    $registration = registerOrganizationTenant('Organization Dependencies', 'org-deps@example.test');

    $this->actingAs($registration['user'], 'tenant')
        ->withSession(['tenant_session_version' => 1]);

    $base = 'http://' . $registration['domain'] . '/organization';

    $this->post($base . '/branches', ['name' => 'Main Branch'])->assertRedirect();
    $branchId = DB::table('branches')->where('tenant_id', $registration['tenant']->id)->value('id');

    $this->post($base . '/departments', [
        'branch_id' => $branchId,
        'name' => 'Operations',
    ])->assertRedirect();
    $departmentId = DB::table('departments')->where('tenant_id', $registration['tenant']->id)->value('id');

    $this->delete($base . '/branches/' . $branchId)->assertStatus(409);

    $this->post($base . '/positions', [
        'department_id' => $departmentId,
        'name' => 'Operations Lead',
    ])->assertRedirect();

    $this->delete($base . '/departments/' . $departmentId)->assertStatus(409);
});
