<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;

uses(RefreshDatabase::class);

test('it registers a company with its first administrator', function () {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [
            'www',
            'admin',
            'platform',
            'api',
        ],
    ]);

    $result = app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme Company',
        adminName: 'John Admin',
        email: 'john@acme.com',
        password: 'StrongPassword123!'
    );

    expect($result['tenant'])->toBeInstanceOf(Tenant::class)
        ->and($result['user'])->toBeInstanceOf(User::class)
        ->and($result['domain'])->toBe('acme-company.humexa.test');

    $this->assertDatabaseHas('tenants', [
        'id' => $result['tenant']->id,
    ]);

    $this->assertDatabaseHas('domains', [
        'tenant_id' => $result['tenant']->id,
        'domain' => 'acme-company.humexa.test',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@acme.com',
        'tenant_id' => $result['tenant']->id,
        'account_type' => User::ACCOUNT_TYPE_TENANT,
    ]);

    expect($result['tenant']->company_name)->toBe('Acme Company')
        ->and(Hash::check(
            'StrongPassword123!',
            $result['user']->password
        ))->toBeTrue();
});

test('it generates another subdomain when the company slug already exists', function () {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
    ]);

    $existingTenant = Tenant::create([
        'company_name' => 'Existing Company',
    ]);

    $existingTenant->createDomain('acme.humexa.test');

    $result = app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme',
        adminName: 'Jane Admin',
        email: 'jane@acme.com',
        password: 'StrongPassword123!'
    );

    expect($result['domain'])->toBe('acme-2.humexa.test');
});


test('it prevents registering an email that already exists', function () {
    $action = app(RegisterCompanyAction::class);

    // Register the first company.
    $first = $action->execute(
        companyName: 'First Company',
        adminName: 'First Admin',
        email: 'admin@example.com',
        password: 'StrongPassword123!'
    );

    // Try registering another company with the same email.
    expect(fn () => $action->execute(
        companyName: 'Second Company',
        adminName: 'Second Admin',
        email: 'admin@example.com',
        password: 'StrongPassword123!'
    ))->toThrow(QueryException::class);

    // The failed registration must be rolled back completely.
    $this->assertDatabaseCount('tenants', 1);
    $this->assertDatabaseCount('domains', 1);
    $this->assertDatabaseCount('users', 1);

    $this->assertDatabaseHas('users', [
        'id' => $first['user']->id,
        'email' => 'admin@example.com',
    ]);
});
