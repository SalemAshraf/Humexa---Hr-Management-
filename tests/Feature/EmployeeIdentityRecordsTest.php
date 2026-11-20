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
        'employee.identity_hash_key' => 'test-identity-hmac-key-at-least-32-characters',
    ]);
});

function registerIdentityRecordTenant(string $company, string $email): array
{
    return app(RegisterCompanyAction::class)->execute(
        companyName: $company,
        adminName: $company . ' Admin',
        email: $email,
        password: 'StrongPassword123!',
    );
}

function identityRecordEmployeePayload(): array
{
    return [
        'first_name' => 'Identity',
        'last_name' => 'Test',
        'employment_status' => 'draft',
    ];
}

function identityRecordPayload(array $overrides = []): array
{
    return array_merge([
        'identity_type' => 'national_id',
        'identity_number' => '29801011234567',
        'issuing_country' => 'Egypt',
        'issue_date' => '2018-01-01',
        'expiry_date' => null,
    ], $overrides);
}

function createIdentityRecordTestEmployee($test, array $registration): int
{
    $test->post(
        'http://' . $registration['domain'] . '/employees',
        identityRecordEmployeePayload()
    )->assertRedirect()->assertSessionHasNoErrors();

    return (int) DB::table('employees')
        ->where('tenant_id', $registration['tenant']->id)
        ->latest('id')
        ->value('id');
}

function actAsIdentityRecordTenant($test, array $registration): void
{
    $test->actingAs($registration['user'], 'tenant')
        ->withSession(['tenant_session_version' => 1]);
}

test('tenant can save identity records and list only masked values', function () {
    $registration = registerIdentityRecordTenant('Identity Alpha', 'identity-alpha@example.test');
    actAsIdentityRecordTenant($this, $registration);

    $employeeId = createIdentityRecordTestEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/identity-records';

    $this->post($base, identityRecordPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->getJson($base)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.identity_type', 'national_id')
        ->assertJsonPath('data.0.masked_number', '••••••••••4567')
        ->assertJsonPath('data.0.is_current', true)
        ->assertJsonMissingPath('data.0.identity_number')
        ->assertJsonMissingPath('data.0.identity_number_hash');

    $stored = DB::table('employee_identity_records')->first();

    expect($stored->identity_number)->not->toBe('29801011234567')
        ->and($stored->identity_number_hash)->toMatch('/^[a-f0-9]{64}$/');
});

test('replacing an identity number retains the previous record as history', function () {
    $registration = registerIdentityRecordTenant('Identity History', 'identity-history@example.test');
    actAsIdentityRecordTenant($this, $registration);

    $employeeId = createIdentityRecordTestEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/identity-records';

    $this->post($base, identityRecordPayload())->assertRedirect();
    $this->post($base, identityRecordPayload([
        'identity_number' => 'P12345678',
        'identity_type' => 'passport',
        'expiry_date' => '2030-01-01',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    // National ID and passport are separate identity types, so each remains current.
    $this->assertDatabaseCount('employee_identity_records', 2);
    $this->assertDatabaseHas('employee_identity_records', [
        'employee_id' => $employeeId,
        'identity_type' => 'national_id',
        'is_current' => true,
    ]);
    $this->assertDatabaseHas('employee_identity_records', [
        'employee_id' => $employeeId,
        'identity_type' => 'passport',
        'is_current' => true,
    ]);

    $this->post($base, identityRecordPayload([
        'identity_number' => '29801019876543',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseCount('employee_identity_records', 3);
    $this->assertDatabaseHas('employee_identity_records', [
        'employee_id' => $employeeId,
        'identity_type' => 'national_id',
        'identity_number_hash' => hash_hmac(
            'sha256',
            'national_id:29801011234567',
            'test-identity-hmac-key-at-least-32-characters'
        ),
        'is_current' => false,
    ]);
    $this->assertDatabaseHas('employee_identity_records', [
        'employee_id' => $employeeId,
        'identity_type' => 'national_id',
        'identity_number_hash' => hash_hmac(
            'sha256',
            'national_id:29801019876543',
            'test-identity-hmac-key-at-least-32-characters'
        ),
        'is_current' => true,
    ]);
});

test('saving the same identity number updates metadata without duplicating history', function () {
    $registration = registerIdentityRecordTenant('Identity Same', 'identity-same@example.test');
    actAsIdentityRecordTenant($this, $registration);

    $employeeId = createIdentityRecordTestEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/identity-records';

    $this->post($base, identityRecordPayload())->assertRedirect();
    $this->post($base, identityRecordPayload([
        'identity_number' => '2980-1011 234567',
        'issuing_country' => 'Egypt Updated',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseCount('employee_identity_records', 1);
    $this->assertDatabaseHas('employee_identity_records', [
        'employee_id' => $employeeId,
        'issuing_country' => 'Egypt Updated',
        'is_current' => true,
    ]);
});

test('tenant cannot reuse an identity number assigned to another employee', function () {
    $registration = registerIdentityRecordTenant('Identity Duplicate', 'identity-duplicate@example.test');
    actAsIdentityRecordTenant($this, $registration);

    $firstEmployeeId = createIdentityRecordTestEmployee($this, $registration);
    $secondEmployeeId = createIdentityRecordTestEmployee($this, $registration);

    $firstBase = 'http://' . $registration['domain'] . '/employees/' . $firstEmployeeId . '/identity-records';
    $secondBase = 'http://' . $registration['domain'] . '/employees/' . $secondEmployeeId . '/identity-records';

    $this->post($firstBase, identityRecordPayload())->assertRedirect();
    $this->post($secondBase, identityRecordPayload())
        ->assertSessionHasErrors('identity_number');

    $this->assertDatabaseCount('employee_identity_records', 1);
});

test('tenant cannot access another tenant identity records', function () {
    $owner = registerIdentityRecordTenant('Identity Owner', 'identity-owner@example.test');
    $intruder = registerIdentityRecordTenant('Identity Intruder', 'identity-intruder@example.test');

    actAsIdentityRecordTenant($this, $owner);
    $employeeId = createIdentityRecordTestEmployee($this, $owner);
    $ownerBase = 'http://' . $owner['domain'] . '/employees/' . $employeeId . '/identity-records';
    $this->post($ownerBase, identityRecordPayload())->assertRedirect();

    actAsIdentityRecordTenant($this, $intruder);
    $intruderBase = 'http://' . $intruder['domain'] . '/employees/' . $employeeId . '/identity-records';

    $this->getJson($intruderBase)->assertNotFound();
    $this->post($intruderBase, identityRecordPayload())->assertNotFound();

    $this->assertDatabaseCount('employee_identity_records', 1);
});

test('identity validation rejects unsupported type, short number, and expiry before issue date', function () {
    $registration = registerIdentityRecordTenant('Identity Validation', 'identity-validation@example.test');
    actAsIdentityRecordTenant($this, $registration);

    $employeeId = createIdentityRecordTestEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/identity-records';

    $this->post($base, identityRecordPayload([
        'identity_type' => 'driver_license',
        'identity_number' => '123',
        'issue_date' => '2024-01-01',
        'expiry_date' => '2023-01-01',
    ]))->assertSessionHasErrors(['identity_type', 'identity_number', 'expiry_date']);

    $this->assertDatabaseCount('employee_identity_records', 0);
});
