<?php

declare(strict_types=1);

use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
        'tenancy.central_domains' => ['humexa.test'],
    ]);
});

function registerEmployeeTenant(string $company, string $email): array
{
    return app(RegisterCompanyAction::class)->execute(
        companyName: $company,
        adminName: $company . ' Admin',
        email: $email,
        password: 'StrongPassword123!',
    );
}

function actAsEmployeeTenant($test, array $registration): void
{
    $test->actingAs($registration['user'], 'tenant')
        ->withSession(['tenant_session_version' => 1]);
}

function employeePayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Salem',
        'middle_name' => 'Ashraf',
        'last_name' => 'Khudair',
        'employment_status' => 'draft',
    ], $overrides);
}

test('tenant can create an employee without a login account and receives an automatic employee number', function () {
    $registration = registerEmployeeTenant('Employees Alpha', 'employees-alpha@example.test');
    actAsEmployeeTenant($this, $registration);

    $base = 'http://' . $registration['domain'] . '/employees';

    $this->post($base, employeePayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $employee = DB::table('employees')
        ->where('tenant_id', $registration['tenant']->id)
        ->first();

    expect($employee)->not->toBeNull()
        ->and($employee->employee_number)->toBe('EMP-0001')
        ->and($employee->user_id)->toBeNull()
        ->and($employee->employment_status)->toBe('draft');

    $this->assertDatabaseHas('employee_employment_histories', [
        'tenant_id' => $registration['tenant']->id,
        'employee_id' => $employee->id,
        'previous_status' => null,
        'new_status' => 'draft',
    ]);
});

test('tenant can set a manual employee number and duplicate numbers are rejected within the same tenant', function () {
    $registration = registerEmployeeTenant('Employees Manual', 'employees-manual@example.test');
    actAsEmployeeTenant($this, $registration);

    $base = 'http://' . $registration['domain'] . '/employees';

    $this->post($base, employeePayload(['employee_number' => 'ALEX-100']))
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->post($base, employeePayload([
        'first_name' => 'Another',
        'employee_number' => 'ALEX-100',
    ]))->assertSessionHasErrors('employee_number');

    $this->assertDatabaseCount('employees', 1);
});

test('employee numbers are isolated between tenants', function () {
    $first = registerEmployeeTenant('Employees First', 'employees-first@example.test');
    $second = registerEmployeeTenant('Employees Second', 'employees-second@example.test');

    actAsEmployeeTenant($this, $first);
    $this->post('http://' . $first['domain'] . '/employees', employeePayload([
        'employee_number' => 'SHARED-01',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    actAsEmployeeTenant($this, $second);
    $this->post('http://' . $second['domain'] . '/employees', employeePayload([
        'employee_number' => 'SHARED-01',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseCount('employees', 2);
});

test('tenant cannot view or update another tenant employee', function () {
    $first = registerEmployeeTenant('Employees Owner', 'employees-owner@example.test');
    $second = registerEmployeeTenant('Employees Intruder', 'employees-intruder@example.test');

    actAsEmployeeTenant($this, $first);
    $this->post('http://' . $first['domain'] . '/employees', employeePayload())
        ->assertRedirect();

    $employeeId = DB::table('employees')
        ->where('tenant_id', $first['tenant']->id)
        ->value('id');

    actAsEmployeeTenant($this, $second);
    $this->get('http://' . $second['domain'] . '/employees/' . $employeeId)
        ->assertNotFound();

    $this->put('http://' . $second['domain'] . '/employees/' . $employeeId, employeePayload([
        'first_name' => 'Tampered',
    ]))->assertNotFound();

    $this->assertDatabaseHas('employees', [
        'id' => $employeeId,
        'tenant_id' => $first['tenant']->id,
        'first_name' => 'Salem',
    ]);
});

test('employee status changes are recorded in employment history', function () {
    $registration = registerEmployeeTenant('Employees History', 'employees-history@example.test');
    actAsEmployeeTenant($this, $registration);

    $base = 'http://' . $registration['domain'] . '/employees';

    $this->post($base, employeePayload())->assertRedirect();
    $employee = DB::table('employees')->where('tenant_id', $registration['tenant']->id)->first();

    $this->put($base . '/' . $employee->id, employeePayload([
        'employment_status' => 'active',
        'hire_date' => '2026-11-01',
        'status_change_reason' => 'Probation completed.',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('employee_employment_histories', [
        'tenant_id' => $registration['tenant']->id,
        'employee_id' => $employee->id,
        'previous_status' => 'draft',
        'new_status' => 'active',
        'reason' => 'Probation completed.',
    ]);
});

test('employee assignment cannot mix branches and departments or use a foreign tenant user', function () {
    $first = registerEmployeeTenant('Employees Structure A', 'employees-structure-a@example.test');
    $second = registerEmployeeTenant('Employees Structure B', 'employees-structure-b@example.test');

    $branchId = DB::table('branches')->insertGetId([
        'tenant_id' => $first['tenant']->id,
        'name' => 'First Branch',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $departmentId = DB::table('departments')->insertGetId([
        'tenant_id' => $first['tenant']->id,
        'branch_id' => $branchId,
        'name' => 'First Department',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actAsEmployeeTenant($this, $second);
    $this->post('http://' . $second['domain'] . '/employees', employeePayload([
        'branch_id' => $branchId,
        'department_id' => $departmentId,
        'user_id' => $first['user']->id,
    ]))->assertSessionHasErrors(['branch_id', 'department_id', 'user_id']);

    $this->assertDatabaseMissing('employees', [
        'tenant_id' => $second['tenant']->id,
        'first_name' => 'Salem',
    ]);
});


test('organization records assigned to employees cannot be archived', function () {
    $registration = registerEmployeeTenant('Employees Organization Guard', 'employees-org-guard@example.test');
    actAsEmployeeTenant($this, $registration);

    $tenantId = $registration['tenant']->id;
    $branchId = DB::table('branches')->insertGetId([
        'tenant_id' => $tenantId,
        'name' => 'Employee Branch',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $departmentId = DB::table('departments')->insertGetId([
        'tenant_id' => $tenantId,
        'name' => 'Employee Department',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $positionDepartmentId = DB::table('departments')->insertGetId([
        'tenant_id' => $tenantId,
        'name' => 'Position Department',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $positionId = DB::table('positions')->insertGetId([
        'tenant_id' => $tenantId,
        'department_id' => $positionDepartmentId,
        'name' => 'Assigned Position',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $base = 'http://' . $registration['domain'];
    $this->post($base . '/employees', employeePayload([
        'first_name' => 'Branch',
        'branch_id' => $branchId,
    ]))->assertRedirect();

    $this->post($base . '/employees', employeePayload([
        'first_name' => 'Department',
        'department_id' => $departmentId,
    ]))->assertRedirect();

    $this->post($base . '/employees', employeePayload([
        'first_name' => 'Position',
        'department_id' => $positionDepartmentId,
        'position_id' => $positionId,
    ]))->assertRedirect();

    $this->delete($base . '/organization/branches/' . $branchId)->assertStatus(409);
    $this->delete($base . '/organization/departments/' . $departmentId)->assertStatus(409);
    $this->delete($base . '/organization/positions/' . $positionId)->assertStatus(409);
});
