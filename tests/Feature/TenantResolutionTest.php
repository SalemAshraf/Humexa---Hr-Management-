<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTenantUser;
use App\Models\User;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
    ]);

    Route::domain('{tenant}.humexa.test')
        ->middleware([
            'web',
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            'auth:tenant',
            EnsureTenantUser::class,
        ])
        ->get('/_test/protected', function () {
            return response('Protected tenant OK', 200);
        });
});

test('it initializes tenancy for a registered subdomain', function () {
    $result = app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme Company',
        adminName: 'John Admin',
        email: 'john@acme.com',
        password: 'StrongPassword123!',
    );

    $tenant = $result['tenant'];

    $this->get('http://acme-company.humexa.test/')
        ->assertOk()
        ->assertSee('Tenant ID: ' . $tenant->id);
});

test('it rejects an unregistered subdomain', function () {
    $this->get('http://unknown.humexa.test/')
        ->assertNotFound();
});

test('it serves the central welcome page without initializing tenancy', function () {
    $this->get('http://humexa.test/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
        );
});

test('tenant user can access protected route on own tenant domain', function () {
    $result = app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme Company',
        adminName: 'John Admin',
        email: 'john@acme.com',
        password: 'StrongPassword123!',
    );

    $user = $result['user'];

    $this->actingAs($user, 'tenant')
        ->get('http://acme-company.humexa.test/_test/protected')
        ->assertOk()
        ->assertSee('Protected tenant OK');
});

test('tenant user cannot access another tenant domain', function () {
    $first = app(RegisterCompanyAction::class)->execute(
        companyName: 'Alpha Company',
        adminName: 'Alpha Admin',
        email: 'alpha@example.com',
        password: 'StrongPassword123!',
    );

    app(RegisterCompanyAction::class)->execute(
        companyName: 'Beta Company',
        adminName: 'Beta Admin',
        email: 'beta@example.com',
        password: 'StrongPassword123!',
    );

    $this->actingAs($first['user'], 'tenant')
        ->get('http://beta-company.humexa.test/_test/protected')
        ->assertForbidden();
});

test('guest cannot access protected tenant route', function () {
    app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme Company',
        adminName: 'John Admin',
        email: 'john@acme.com',
        password: 'StrongPassword123!',
    );

    $this->get('http://acme-company.humexa.test/_test/protected')
        ->assertRedirect();
});

test('platform user cannot access protected tenant route', function () {
    app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme Company',
        adminName: 'John Admin',
        email: 'john@acme.com',
        password: 'StrongPassword123!',
    );

    $platformUser = User::factory()->create();

    $platformUser->forceFill([
        'tenant_id' => null,
        'account_type' => User::ACCOUNT_TYPE_PLATFORM,
    ])->save();

    $this->actingAs($platformUser, 'tenant')
        ->get('http://acme-company.humexa.test/_test/protected')
        ->assertForbidden();
});

