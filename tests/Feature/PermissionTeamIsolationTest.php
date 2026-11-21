<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\PlatformTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
    ]);

    $action = app(RegisterCompanyAction::class);

    $this->tenantA = $action->execute(
        companyName: 'Company Alpha',
        adminName: 'Alpha Admin',
        email: 'alpha@example.com',
        password: 'StrongPassword123!',
    )['tenant'];

    $this->tenantB = $action->execute(
        companyName: 'Company Beta',
        adminName: 'Beta Admin',
        email: 'beta@example.com',
        password: 'StrongPassword123!',
    )['tenant'];
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    app(PermissionRegistrar::class)->setPermissionsTeamId(null);

    auth()->guard('platform')->logout();
});

test('tenant roles are isolated between tenants', function () {
    tenancy()->initialize($this->tenantA);

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())
        ->toBe($this->tenantA->id);

    Role::create([
        'name' => 'HR Manager',
        'guard_name' => 'tenant',
    ]);

    tenancy()->end();

    tenancy()->initialize($this->tenantB);

    expect(fn () => Role::findByName('HR Manager', 'tenant'))
        ->toThrow(RoleDoesNotExist::class);

    tenancy()->end();

    tenancy()->initialize($this->tenantA);

    expect(Role::findByName('HR Manager', 'tenant'))
        ->toBeInstanceOf(Role::class);
});

test('platform roles are isolated from tenant roles', function () {
    tenancy()->initialize($this->tenantA);

    Role::create([
        'name' => 'Tenant Administrator',
        'guard_name' => 'tenant',
    ]);

    tenancy()->end();

    app(PermissionRegistrar::class)
        ->setPermissionsTeamId(PlatformTeam::ID);

    Role::create([
        'name' => 'Platform Administrator',
        'guard_name' => 'platform',
    ]);

    $platformUser = User::factory()->create();

    $platformUser->forceFill([
        'tenant_id' => null,
        'account_type' => User::ACCOUNT_TYPE_PLATFORM,
    ])->save();

    $this->actingAs($platformUser, 'platform');

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())
        ->toBe(PlatformTeam::ID);

    expect(Role::findByName('Platform Administrator', 'platform'))
        ->toBeInstanceOf(Role::class);

    expect(fn () => Role::findByName('Tenant Administrator', 'tenant'))
        ->toThrow(RoleDoesNotExist::class);
});

test('it prevents assigning a role from another tenant', function () {
    tenancy()->initialize($this->tenantA);

    $roleA = Role::create([
        'name' => 'HR Manager',
        'guard_name' => 'tenant',
    ]);

    tenancy()->end();

    tenancy()->initialize($this->tenantB);

    $userB = User::where('email', 'beta@example.com')->firstOrFail();

    expect(fn () => $userB->assignRole($roleA))
        ->toThrow(RoleDoesNotExist::class);

    expect($userB->roles()->pluck('name')->all())->not->toContain('HR Manager', 'Foreign HR Manager');
});

test('it preserves existing roles when syncing a role from another tenant', function () {
    tenancy()->initialize($this->tenantA);

    $foreignRole = Role::create([
        'name' => 'Foreign HR Manager',
        'guard_name' => 'tenant',
    ]);

    tenancy()->end();

    tenancy()->initialize($this->tenantB);

    $localRole = Role::create([
        'name' => 'Local HR Manager',
        'guard_name' => 'tenant',
    ]);

    $userB = User::where('email', 'beta@example.com')->firstOrFail();

    $userB->assignRole($localRole);

    expect(fn () => $userB->syncRoles($foreignRole))
        ->toThrow(RoleDoesNotExist::class);

    expect($userB->fresh()->hasRole('Local HR Manager', 'tenant'))
        ->toBeTrue();
});

test('it prevents removing a role from another tenant', function () {
    tenancy()->initialize($this->tenantA);

    $foreignRole = Role::create([
        'name' => 'Foreign HR Manager',
        'guard_name' => 'tenant',
    ]);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    $userB = User::where('email', 'beta@example.com')->firstOrFail();

    expect(fn () => $userB->removeRole($foreignRole))
        ->toThrow(RoleDoesNotExist::class);

    expect($userB->roles()->pluck('name')->all())->not->toContain('HR Manager', 'Foreign HR Manager');
});

test('it prevents granting permissions outside the user tenant context', function () {
    tenancy()->initialize($this->tenantB);

    $permission = Permission::create([
        'name' => 'isolation.test.permission',
        'guard_name' => 'tenant',
    ]);

    $userB = User::where('email', 'beta@example.com')->firstOrFail();

    tenancy()->end();
    tenancy()->initialize($this->tenantA);

    expect(fn () => $userB->givePermissionTo($permission))
        ->toThrow(AuthorizationException::class);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    expect($userB->fresh()->permissions()->exists())->toBeFalse();
});

test('it preserves existing direct permissions when syncing outside tenant context', function () {
    tenancy()->initialize($this->tenantB);

    $existingPermission = Permission::create([
        'name' => 'isolation.test.permission',
        'guard_name' => 'tenant',
    ]);

    $newPermission = Permission::create([
        'name' => 'employees.create',
        'guard_name' => 'tenant',
    ]);

    $userB = User::where('email', 'beta@example.com')->firstOrFail();

    $userB->givePermissionTo($existingPermission);

    tenancy()->end();
    tenancy()->initialize($this->tenantA);

    expect(fn () => $userB->syncPermissions($newPermission))
        ->toThrow(AuthorizationException::class);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    expect($userB->fresh()->hasDirectPermission('isolation.test.permission', 'tenant'))
        ->toBeTrue();
});

test('it prevents revoking permissions outside the user tenant context', function () {
    tenancy()->initialize($this->tenantB);

    $permission = Permission::create([
        'name' => 'isolation.test.permission',
        'guard_name' => 'tenant',
    ]);

    $userB = User::where('email', 'beta@example.com')->firstOrFail();

    $userB->givePermissionTo($permission);

    tenancy()->end();
    tenancy()->initialize($this->tenantA);

    expect(fn () => $userB->revokePermissionTo($permission))
        ->toThrow(AuthorizationException::class);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    expect($userB->fresh()->hasDirectPermission('isolation.test.permission', 'tenant'))
        ->toBeTrue();
});