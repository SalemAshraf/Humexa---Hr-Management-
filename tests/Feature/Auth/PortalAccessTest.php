<?php

declare(strict_types=1);

use App\Http\Middleware\EnsurePlatformUser;
use App\Http\Middleware\EnsureTenantUser;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
    ]);

    Route::middleware(['web', 'auth', EnsureTenantUser::class])
        ->get('/_test/tenant-portal', fn () => response('Tenant OK', 200));

    Route::middleware(['web', 'auth', EnsurePlatformUser::class])
        ->get('/_test/platform-portal', fn () => response('Platform OK', 200));
});

function createPortalTenant(string $name): Tenant
{
    return app(\App\Modules\Tenants\Actions\RegisterCompanyAction::class)
        ->execute(
            companyName: $name,
            adminName: $name . ' Admin',
            email: strtolower(str_replace(' ', '.', $name)) . '@example.com',
            password: 'StrongPassword123!',
        )['tenant'];
}

function createPlatformUser(): User
{
    $user = User::factory()->create();

    $user->forceFill([
        'tenant_id' => null,
        'account_type' => User::ACCOUNT_TYPE_PLATFORM,
    ])->save();

    return $user;
}

test('tenant user can access their own tenant portal', function () {
    $tenant = createPortalTenant('Company Alpha');

    $user = User::where('email', 'company.alpha@example.com')->firstOrFail();

    tenancy()->initialize($tenant);

    $response = $this->actingAs($user, 'tenant')
        ->get('/_test/tenant-portal');

    $response->assertOk()
        ->assertSee('Tenant OK');

    tenancy()->end();
});

test('tenant user cannot access another tenant portal', function () {
    $tenantA = createPortalTenant('Company Alpha');
    $tenantB = createPortalTenant('Company Beta');

    $user = User::where('email', 'company.alpha@example.com')->firstOrFail();

    tenancy()->initialize($tenantB);

    $response = $this->actingAs($user, 'tenant')
        ->get('/_test/tenant-portal');

    $response->assertForbidden();

    tenancy()->end();
});

test('platform user cannot access tenant portal', function () {
    $tenant = createPortalTenant('Company Alpha');
    $platformUser = createPlatformUser();

    tenancy()->initialize($tenant);

    $response = $this->actingAs($platformUser, 'platform')
        ->get('/_test/tenant-portal');

    $response->assertForbidden();

    tenancy()->end();
});

test('tenant user cannot access platform portal', function () {
    createPortalTenant('Company Alpha');

    $tenantUser = User::where('email', 'company.alpha@example.com')->firstOrFail();

    $response = $this->actingAs($tenantUser)
        ->get('/_test/platform-portal');

    $response->assertForbidden();
});

test('platform user can access platform portal', function () {
    $platformUser = createPlatformUser();

    $response = $this->actingAs($platformUser, 'platform')
        ->get('/_test/platform-portal');

    $response->assertOk()
        ->assertSee('Platform OK');
});

test('guest cannot access either portal', function () {
    $tenant = createPortalTenant('Company Alpha');

    tenancy()->initialize($tenant);

    $tenantResponse = $this->get('/_test/tenant-portal');

    $tenantResponse->assertRedirect();

    tenancy()->end();

    $platformResponse = $this->get('/_test/platform-portal');

    $platformResponse->assertRedirect();
});



test('tenant user can login to their own tenant portal', function () {
    $tenant = createPortalTenant('Company Alpha');

    $user = User::where('email', 'company.alpha@example.com')->firstOrFail();

    $response = $this->post('http://company-alpha.humexa.test/login', [
        'email' => $user->email,
        'password' => 'StrongPassword123!',
    ]);

    $response->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user, 'tenant');
});

test('tenant user cannot login to another tenant portal', function () {
    createPortalTenant('Company Alpha');
    createPortalTenant('Company Beta');

    $user = User::where('email', 'company.alpha@example.com')->firstOrFail();

    $response = $this->post('http://company-beta.humexa.test/login', [
        'email' => $user->email,
        'password' => 'StrongPassword123!',
    ]);

    $response->assertSessionHasErrors('email');

    $this->assertGuest('tenant');
});

test('platform user can login to platform portal', function () {
    $platformUser = createPlatformUser();

    $response = $this->post('/platform/login', [
        'email' => $platformUser->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/platform/dashboard');

    $this->assertAuthenticatedAs($platformUser, 'platform');
});

test('tenant user cannot login to platform portal', function () {
    createPortalTenant('Company Alpha');

    $tenantUser = User::where('email', 'company.alpha@example.com')->firstOrFail();

    $response = $this->post('/platform/login', [
        'email' => $tenantUser->email,
        'password' => 'StrongPassword123!',
    ]);

    $response->assertSessionHasErrors('email');

    $this->assertGuest('platform');
});

test('portal login rejects an incorrect password', function () {
    createPortalTenant('Company Alpha');

    $user = User::where('email', 'company.alpha@example.com')->firstOrFail();

    $response = $this->post('http://company-alpha.humexa.test/login', [
        'email' => $user->email,
        'password' => 'WrongPassword123!',
    ]);

    $response->assertSessionHasErrors('email');

    $this->assertGuest('tenant');
});



test('tenant login page receives tenant-specific props', function () {
    createPortalTenant('Company Alpha');

    $response = $this->get('http://company-alpha.humexa.test/login');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/Login')
            ->where('canResetPassword', false)
            ->where('showRegistrationLink', true)
            ->where('loginUrl', fn ($url) => str_ends_with($url, '/login'))
        );
});

test('platform login page receives platform-specific props', function () {
    $response = $this->get('/platform/login');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/Login')
            ->where('canResetPassword', false)
            ->where('showRegistrationLink', false)
            ->where('loginUrl', fn ($url) => str_ends_with($url, '/platform/login'))
        );
});


test('tenant user can logout from their own portal', function () {
    $tenant = createPortalTenant('Company Alpha');

    $user = User::where('email', 'company.alpha@example.com')->firstOrFail();

    $this->actingAs($user, 'tenant');

    $this->assertAuthenticatedAs($user, 'tenant');

    $response = $this->post('http://company-alpha.humexa.test/logout');

    $response->assertRedirect();

    $this->assertGuest('tenant');
});

test('platform user can logout from platform portal', function () {
    $user = createPlatformUser();

    $this->actingAs($user, 'platform');

    $this->assertAuthenticatedAs($user, 'platform');

    $response = $this->post('/platform/logout');

    $response->assertRedirect();

    $this->assertGuest('platform');
});


test('guest is redirected to the tenant login page on tenant domain', function () {
    createPortalTenant('Company Alpha');

    $response = $this->get('http://company-alpha.humexa.test/dashboard');

    $response->assertRedirect('http://company-alpha.humexa.test/login');
});

test('guest is redirected to the platform login page', function () {
    $response = $this->get('/platform/dashboard');

    $response->assertRedirect('/platform/login');
});

test('guest is redirected to the central login page', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});
