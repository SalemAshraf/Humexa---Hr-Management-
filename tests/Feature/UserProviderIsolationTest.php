<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Users\Providers\PlatformUserProvider;
use App\Modules\Users\Providers\TenantUserProvider;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

function createTenantForProviderTest(string $company, string $email): array
{
    return app(RegisterCompanyAction::class)->execute(
        companyName: $company,
        adminName: 'Test Admin',
        email: $email,
        password: 'StrongPassword123!',
    );
}

test('tenant provider returns no users without initialized tenancy', function () {
    $result = createTenantForProviderTest(
        'Acme Company',
        'admin@acme.test'
    );

    $provider = new TenantUserProvider(
        Hash::driver(),
        User::class
    );

    expect($provider->retrieveByCredentials([
        'email' => $result['user']->email,
    ]))->toBeNull();
});

test('tenant provider only retrieves users belonging to current tenant', function () {
    $first = createTenantForProviderTest(
        'Alpha Company',
        'admin@alpha.test'
    );

    $second = createTenantForProviderTest(
        'Beta Company',
        'admin@beta.test'
    );

    $provider = new TenantUserProvider(
        Hash::driver(),
        User::class
    );

    tenancy()->initialize($first['tenant']);

    expect($provider->retrieveByCredentials([
        'email' => $first['user']->email,
    ]))->not->toBeNull();

    expect($provider->retrieveByCredentials([
        'email' => $second['user']->email,
    ]))->toBeNull();
});

test('platform provider only retrieves platform users outside tenant context', function () {
    $tenant = createTenantForProviderTest(
        'Acme Company',
        'admin@acme.test'
    );

    $platformUser = User::factory()->create();

    $platformUser->forceFill([
        'tenant_id' => null,
        'account_type' => User::ACCOUNT_TYPE_PLATFORM,
    ])->save();

    $provider = new PlatformUserProvider(
        Hash::driver(),
        User::class
    );

    expect($provider->retrieveByCredentials([
        'email' => $platformUser->email,
    ]))->not->toBeNull();

    expect($provider->retrieveByCredentials([
        'email' => $tenant['user']->email,
    ]))->toBeNull();
});

test('platform provider returns no users inside tenant context', function () {
    $tenant = createTenantForProviderTest(
        'Acme Company',
        'admin@acme.test'
    );

    $platformUser = User::factory()->create();

    $platformUser->forceFill([
        'tenant_id' => null,
        'account_type' => User::ACCOUNT_TYPE_PLATFORM,
    ])->save();

    tenancy()->initialize($tenant['tenant']);

    $provider = new PlatformUserProvider(
        Hash::driver(),
        User::class
    );

    expect($provider->retrieveByCredentials([
        'email' => $platformUser->email,
    ]))->toBeNull();
});
