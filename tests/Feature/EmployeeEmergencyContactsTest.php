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

function registerEmergencyContactTenant(string $company, string $email): array
{
    return app(RegisterCompanyAction::class)->execute(
        companyName: $company,
        adminName: $company . ' Admin',
        email: $email,
        password: 'StrongPassword123!',
    );
}

function emergencyContactEmployeePayload(): array
{
    return [
        'first_name' => 'Emergency',
        'last_name' => 'Contact',
        'employment_status' => 'draft',
    ];
}

function emergencyContactPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Ahmed Mohamed',
        'relationship' => 'Brother',
        'phone' => '+201001234567',
        'alternate_phone' => null,
        'email' => 'ahmed@example.test',
        'notes' => null,
    ], $overrides);
}

function createEmergencyContactTestEmployee($test, array $registration): int
{
    $test->post(
        'http://' . $registration['domain'] . '/employees',
        emergencyContactEmployeePayload()
    )->assertRedirect()->assertSessionHasNoErrors();

    return (int) DB::table('employees')
        ->where('tenant_id', $registration['tenant']->id)
        ->latest('id')
        ->value('id');
}

function actAsEmergencyContactTenant($test, array $registration): void
{
    $test->actingAs($registration['user'], 'tenant')
        ->withSession(['tenant_session_version' => 1]);
}

test('tenant can create and list multiple emergency contacts in priority order', function () {
    $registration = registerEmergencyContactTenant('Emergency Alpha', 'emergency-alpha@example.test');
    actAsEmergencyContactTenant($this, $registration);

    $employeeId = createEmergencyContactTestEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/emergency-contacts';

    $this->post($base, emergencyContactPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->post($base, emergencyContactPayload([
        'full_name' => 'Mona Mohamed',
        'relationship' => 'Mother',
        'phone' => '+201112223333',
        'email' => null,
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $this->getJson($base)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.priority', 1)
        ->assertJsonPath('data.1.priority', 2);
});

test('tenant can update and delete an emergency contact', function () {
    $registration = registerEmergencyContactTenant('Emergency CRUD', 'emergency-crud@example.test');
    actAsEmergencyContactTenant($this, $registration);

    $employeeId = createEmergencyContactTestEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/emergency-contacts';

    $this->post($base, emergencyContactPayload())->assertRedirect();
    $contactId = (int) DB::table('employee_emergency_contacts')
        ->where('employee_id', $employeeId)
        ->value('id');

    $this->put($base . '/' . $contactId, emergencyContactPayload([
        'full_name' => 'Updated Contact',
        'phone' => '+201009998887',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('employee_emergency_contacts', [
        'id' => $contactId,
        'full_name' => 'Updated Contact',
        'phone' => '+201009998887',
    ]);

    $this->delete($base . '/' . $contactId)->assertRedirect();

    $this->assertDatabaseMissing('employee_emergency_contacts', [
        'id' => $contactId,
    ]);
});

test('tenant cannot access another tenant emergency contacts', function () {
    $owner = registerEmergencyContactTenant('Emergency Owner', 'emergency-owner@example.test');
    $intruder = registerEmergencyContactTenant('Emergency Intruder', 'emergency-intruder@example.test');

    actAsEmergencyContactTenant($this, $owner);
    $employeeId = createEmergencyContactTestEmployee($this, $owner);

    actAsEmergencyContactTenant($this, $intruder);
    $base = 'http://' . $intruder['domain'] . '/employees/' . $employeeId . '/emergency-contacts';

    $this->getJson($base)->assertNotFound();
    $this->post($base, emergencyContactPayload())->assertNotFound();

    $this->assertDatabaseCount('employee_emergency_contacts', 0);
});

test('tenant cannot update or delete an emergency contact belonging to another employee', function () {
    $registration = registerEmergencyContactTenant('Emergency Ownership', 'emergency-ownership@example.test');
    actAsEmergencyContactTenant($this, $registration);

    $firstEmployeeId = createEmergencyContactTestEmployee($this, $registration);
    $secondEmployeeId = createEmergencyContactTestEmployee($this, $registration);

    $firstBase = 'http://' . $registration['domain'] . '/employees/' . $firstEmployeeId . '/emergency-contacts';
    $this->post($firstBase, emergencyContactPayload())->assertRedirect();

    $contactId = (int) DB::table('employee_emergency_contacts')
        ->where('employee_id', $firstEmployeeId)
        ->value('id');

    $secondBase = 'http://' . $registration['domain'] . '/employees/' . $secondEmployeeId . '/emergency-contacts';

    $this->put($secondBase . '/' . $contactId, emergencyContactPayload([
        'full_name' => 'Tampered Contact',
    ]))->assertNotFound();

    $this->delete($secondBase . '/' . $contactId)->assertNotFound();

    $this->assertDatabaseHas('employee_emergency_contacts', [
        'id' => $contactId,
        'employee_id' => $firstEmployeeId,
        'full_name' => 'Ahmed Mohamed',
    ]);
});

test('emergency contact validation rejects invalid required fields and email', function () {
    $registration = registerEmergencyContactTenant('Emergency Validation', 'emergency-validation@example.test');
    actAsEmergencyContactTenant($this, $registration);

    $employeeId = createEmergencyContactTestEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/emergency-contacts';

    $this->post($base, emergencyContactPayload([
        'full_name' => '',
        'phone' => '',
        'email' => 'not-an-email',
    ]))->assertSessionHasErrors(['full_name', 'phone', 'email']);

    $this->assertDatabaseCount('employee_emergency_contacts', 0);
});
