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

function registerAddressTenant(string $company, string $email): array
{
    return app(RegisterCompanyAction::class)->execute(
        companyName: $company,
        adminName: $company . ' Admin',
        email: $email,
        password: 'StrongPassword123!',
    );
}

function addressEmployeePayload(): array
{
    return [
        'first_name' => 'Address',
        'last_name' => 'Test',
        'employment_status' => 'draft',
    ];
}

function employeeAddressPayload(array $overrides = []): array
{
    return array_merge([
        'address_type' => 'residential',
        'address_line_1' => '12 Example Street',
        'address_line_2' => null,
        'city' => 'Alexandria',
        'state' => null,
        'postal_code' => '21500',
        'country' => 'Egypt',
        'is_primary' => false,
    ], $overrides);
}

function createAddressTestEmployee($test, array $registration): int
{
    $test->post('http://' . $registration['domain'] . '/employees', addressEmployeePayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    return (int) DB::table('employees')
        ->where('tenant_id', $registration['tenant']->id)
        ->value('id');
}

function actAsAddressTenant($test, array $registration): void
{
    $test->actingAs($registration['user'], 'tenant')
        ->withSession(['tenant_session_version' => 1]);
}

test('tenant can create and list multiple employee addresses', function () {
    $registration = registerAddressTenant('Address Alpha', 'address-alpha@example.test');
    actAsAddressTenant($this, $registration);

    $employeeId = createAddressTestEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/addresses';

    $this->post($base, employeeAddressPayload(['is_primary' => true]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->post($base, employeeAddressPayload([
        'address_type' => 'mailing',
        'address_line_1' => '34 Second Street',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $this->getJson($base)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.is_primary', true);
});

test('setting a new primary address clears the previous primary atomically', function () {
    $registration = registerAddressTenant('Address Primary', 'address-primary@example.test');
    actAsAddressTenant($this, $registration);

    $employeeId = createAddressTestEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/addresses';

    $this->post($base, employeeAddressPayload(['is_primary' => true]))->assertRedirect();
    $firstId = DB::table('employee_addresses')->where('employee_id', $employeeId)->value('id');

    $this->post($base, employeeAddressPayload([
        'address_type' => 'mailing',
        'address_line_1' => '99 New Street',
        'is_primary' => true,
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect(DB::table('employee_addresses')
        ->where('employee_id', $employeeId)
        ->where('is_primary', true)
        ->count())->toBe(1);

    $this->assertDatabaseHas('employee_addresses', [
        'id' => $firstId,
        'is_primary' => false,
    ]);
});

test('tenant cannot access another tenant employee addresses', function () {
    $owner = registerAddressTenant('Address Owner', 'address-owner@example.test');
    $intruder = registerAddressTenant('Address Intruder', 'address-intruder@example.test');

    actAsAddressTenant($this, $owner);
    $employeeId = createAddressTestEmployee($this, $owner);

    actAsAddressTenant($this, $intruder);
    $base = 'http://' . $intruder['domain'] . '/employees/' . $employeeId . '/addresses';

    $this->getJson($base)->assertNotFound();
    $this->post($base, employeeAddressPayload(['is_primary' => true]))->assertNotFound();

    $this->assertDatabaseCount('employee_addresses', 0);
});

test('tenant cannot update or delete an address belonging to another employee', function () {
    $registration = registerAddressTenant('Address Ownership', 'address-ownership@example.test');
    actAsAddressTenant($this, $registration);

    $firstEmployeeId = createAddressTestEmployee($this, $registration);
    $secondEmployeeId = createAddressTestEmployee($this, $registration);

    $firstBase = 'http://' . $registration['domain'] . '/employees/' . $firstEmployeeId . '/addresses';
    $this->post($firstBase, employeeAddressPayload())->assertRedirect();

    $addressId = (int) DB::table('employee_addresses')
        ->where('employee_id', $firstEmployeeId)
        ->value('id');

    $secondBase = 'http://' . $registration['domain'] . '/employees/' . $secondEmployeeId . '/addresses';

    $this->put($secondBase . '/' . $addressId, employeeAddressPayload([
        'address_line_1' => 'Tampered Street',
    ]))->assertNotFound();

    $this->delete($secondBase . '/' . $addressId)->assertNotFound();

    $this->assertDatabaseHas('employee_addresses', [
        'id' => $addressId,
        'employee_id' => $firstEmployeeId,
        'address_line_1' => '12 Example Street',
    ]);
});

test('employee address validation rejects missing required fields', function () {
    $registration = registerAddressTenant('Address Validation', 'address-validation@example.test');
    actAsAddressTenant($this, $registration);

    $employeeId = createAddressTestEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/addresses';

    $this->post($base, [
        'address_type' => 'residential',
        'address_line_1' => '',
        'city' => 'Alexandria',
        'country' => 'Egypt',
        'is_primary' => true,
    ])->assertSessionHasErrors('address_line_1');

    $this->assertDatabaseCount('employee_addresses', 0);
});
