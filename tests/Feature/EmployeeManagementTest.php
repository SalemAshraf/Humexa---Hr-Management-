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

    $this->get($base)->assertOk()->assertInertia(fn ($page) => $page->component('Employees/Index')->where('summary.total', 0));
    $this->get($base . '/create')->assertOk()->assertInertia(fn ($page) => $page->component('Employees/Create'));

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

    $this->get($base . '/' . $employee->id)->assertOk()->assertInertia(fn ($page) => $page->component('Employees/Show'));
    $this->get($base . '/' . $employee->id . '/edit')->assertOk()->assertInertia(fn ($page) => $page->component('Employees/Edit'));

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
        'employee_number' => 'EMP-MANUAL-2',
        'hire_date' => '2026-11-01',
        'status_change_reason' => 'Probation completed.',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'employee_number' => 'EMP-MANUAL-2',
    ]);

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


test('tenant can link an employee to one of its own user accounts', function () {
    $registration = registerEmployeeTenant('Employees Account Link', 'employees-account-link@example.test');
    actAsEmployeeTenant($this, $registration);

    $this->post('http://' . $registration['domain'] . '/employees', employeePayload([
        'user_id' => $registration['user']->id,
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('employees', [
        'tenant_id' => $registration['tenant']->id,
        'user_id' => $registration['user']->id,
    ]);
});

test('non-draft employees require a hire date and terminated employees require a termination date', function () {
    $registration = registerEmployeeTenant('Employees Required Dates', 'employees-required-dates@example.test');
    actAsEmployeeTenant($this, $registration);

    $base = 'http://' . $registration['domain'] . '/employees';

    $this->post($base, employeePayload([
        'employment_status' => 'active',
    ]))->assertSessionHasErrors('hire_date');

    $this->post($base, employeePayload([
        'employment_status' => 'terminated',
        'hire_date' => '2026-10-01',
    ]))->assertSessionHasErrors('termination_date');

    $this->assertDatabaseCount('employees', 0);
});

test('employee reporting relationships cannot create a manager cycle', function () {
    $registration = registerEmployeeTenant('Employees Manager Cycle', 'employees-manager-cycle@example.test');
    actAsEmployeeTenant($this, $registration);

    $base = 'http://' . $registration['domain'] . '/employees';

    $this->post($base, employeePayload(['first_name' => 'Manager A']))->assertRedirect();
    $this->post($base, employeePayload(['first_name' => 'Manager B']))->assertRedirect();

    $employees = DB::table('employees')->where('tenant_id', $registration['tenant']->id)->orderBy('id')->get();

    $this->put($base . '/' . $employees[0]->id, employeePayload([
        'first_name' => 'Manager A',
        'manager_id' => $employees[1]->id,
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $this->put($base . '/' . $employees[1]->id, employeePayload([
        'first_name' => 'Manager B',
        'manager_id' => $employees[0]->id,
    ]))->assertSessionHasErrors('manager_id');

    $this->assertDatabaseHas('employees', [
        'id' => $employees[1]->id,
        'manager_id' => null,
    ]);
});

test('employee create and update persist all supported personal contact fields', function () {
    $registration = registerEmployeeTenant('Employees Personal Fields', 'employees-personal-fields@example.test');
    actAsEmployeeTenant($this, $registration);

    $base = 'http://' . $registration['domain'] . '/employees';
    $this->post($base, employeePayload([
        'work_email' => 'employee.work@example.test',
        'personal_email' => 'employee.personal@example.test',
        'phone' => '+201001234567',
        'alternate_phone' => '+201112223333',
        'date_of_birth' => '1998-05-10',
        'gender' => 'male',
        'nationality' => 'Egyptian',
        'marital_status' => 'single',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $employee = DB::table('employees')
        ->where('tenant_id', $registration['tenant']->id)
        ->first();

    expect($employee)->not->toBeNull()
        ->and($employee->personal_email)->toBe('employee.personal@example.test')
        ->and($employee->alternate_phone)->toBe('+201112223333')
        ->and($employee->nationality)->toBe('Egyptian')
        ->and($employee->marital_status)->toBe('single');

    $this->put($base . '/' . $employee->id, employeePayload([
        'personal_email' => 'updated.personal@example.test',
        'alternate_phone' => '+201199988877',
        'nationality' => 'Egypt',
        'marital_status' => 'married',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'personal_email' => 'updated.personal@example.test',
        'alternate_phone' => '+201199988877',
        'nationality' => 'Egypt',
        'marital_status' => 'married',
    ]);
});

test('HR links a login account to an employee: same company only, one employee per account', function () {
    $a = registerEmployeeTenant('Link Alpha', 'link-alpha@example.test');
    $b = registerEmployeeTenant('Link Beta', 'link-beta@example.test');
    actAsEmployeeTenant($this, $a);
    $base = 'http://' . $a['domain'] . '/employees';

    $this->post($base, employeePayload(['first_name' => 'One']))->assertRedirect();
    $this->post($base, employeePayload(['first_name' => 'Two']))->assertRedirect();
    $ids = DB::table('employees')->where('tenant_id', $a['tenant']->id)->orderBy('id')->pluck('id');

    // an account from another company is refused
    $this->put($base . '/' . $ids[0], employeePayload(['first_name' => 'One', 'user_id' => $b['user']->id]))->assertSessionHasErrors('user_id');
    expect(DB::table('employees')->where('id', $ids[0])->value('user_id'))->toBeNull();

    // own company account links
    $this->put($base . '/' . $ids[0], employeePayload(['first_name' => 'One', 'user_id' => $a['user']->id]))->assertSessionHasNoErrors();
    expect((int) DB::table('employees')->where('id', $ids[0])->value('user_id'))->toBe($a['user']->id);

    // the same account cannot be linked to a second employee
    $this->put($base . '/' . $ids[1], employeePayload(['first_name' => 'Two', 'user_id' => $a['user']->id]))->assertSessionHasErrors('user_id');

    // unlinking ends that account's app tokens
    $a['user']->createToken('phone', ['attendance:mobile']);
    $this->put($base . '/' . $ids[0], employeePayload(['first_name' => 'One', 'user_id' => '']))->assertSessionHasNoErrors();
    expect($a['user']->tokens()->count())->toBe(0);
    $this->put($base . '/' . $ids[0], employeePayload(['first_name' => 'One', 'user_id' => $a['user']->id]))->assertSessionHasNoErrors();
    $a['user']->createToken('phone2', ['attendance:mobile']);

    // and it is no longer offered in the picker
    $this->get($base . '/' . $ids[1] . '/edit')->assertInertia(fn ($p) => $p->where('accounts', fn ($acc) => collect($acc)->where('id', $a['user']->id)->isEmpty()));
});
