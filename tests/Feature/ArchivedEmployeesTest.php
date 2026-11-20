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

function registerArchivedEmployeeTenant(string $company, string $email): array
{
    return app(RegisterCompanyAction::class)->execute(
        companyName: $company,
        adminName: $company . ' Admin',
        email: $email,
        password: 'StrongPassword123!',
    );
}

function actAsArchivedEmployeeTenant($test, array $registration): void
{
    $test->actingAs($registration['user'], 'tenant')
        ->withSession(['tenant_session_version' => 1]);
}

function createArchivedEmployee($test, array $registration): int
{
    $test->post('http://' . $registration['domain'] . '/employees', [
        'first_name' => 'Archived',
        'last_name' => 'Employee',
        'employment_status' => 'draft',
    ])->assertRedirect()->assertSessionHasNoErrors();

    return (int) DB::table('employees')
        ->where('tenant_id', $registration['tenant']->id)
        ->latest('id')
        ->value('id');
}

test('tenant can list and restore its archived employee', function () {
    $registration = registerArchivedEmployeeTenant('Archived Restore', 'archived-restore@example.test');
    actAsArchivedEmployeeTenant($this, $registration);

    $employeeId = createArchivedEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId;

    $this->delete($base)->assertRedirect('/employees');
    $this->assertSoftDeleted('employees', ['id' => $employeeId]);

    $this->get('http://' . $registration['domain'] . '/employees/archived')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Employees/Archived')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $employeeId));

    $this->post($base . '/restore')
        ->assertRedirect('/employees/archived')
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('employees', [
        'id' => $employeeId,
        'tenant_id' => $registration['tenant']->id,
        'deleted_at' => null,
    ]);
});

test('tenant cannot restore another tenant archived employee', function () {
    $owner = registerArchivedEmployeeTenant('Archived Owner', 'archived-owner@example.test');
    actAsArchivedEmployeeTenant($this, $owner);
    $employeeId = createArchivedEmployee($this, $owner);
    $this->delete('http://' . $owner['domain'] . '/employees/' . $employeeId)->assertRedirect();

    $other = registerArchivedEmployeeTenant('Archived Other', 'archived-other@example.test');
    actAsArchivedEmployeeTenant($this, $other);

    $this->post('http://' . $other['domain'] . '/employees/' . $employeeId . '/restore')
        ->assertNotFound();

    $this->assertSoftDeleted('employees', ['id' => $employeeId]);
});
