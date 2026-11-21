<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
        'tenancy.central_domains' => ['humexa.test'],
    ]);

    $this->registration = app(RegisterCompanyAction::class)->execute(
        companyName: 'Roles Co',
        adminName: 'Roles Owner',
        email: 'owner@roles.test',
        password: 'StrongPassword123!',
    );

    $this->host = $this->registration['domain'];
});

function tenantUserWithRole(array $registration, string $email, string $role): User
{
    return $registration['tenant']->run(function () use ($registration, $email, $role) {
        $user = new User(['name' => $role . ' user', 'email' => $email, 'password' => 'StrongPassword123!']);
        $user->forceFill([
            'tenant_id' => $registration['tenant']->id,
            'account_type' => User::ACCOUNT_TYPE_TENANT,
        ])->save();
        $user->assignRole($role);

        return $user;
    });
}

function getAs($test, User $user, string $path)
{
    return $test->actingAs($user, 'tenant')
        ->withSession(['tenant_session_version' => 1])
        ->get('http://' . $test->host . $path);
}

test('the company registrant becomes the owner with every permission', function () {
    $permissions = $this->registration['tenant']->run(
        fn () => $this->registration['user']->getAllPermissions()->pluck('name')->sort()->values()->all()
    );

    expect($permissions)->toBe(collect(TenantRoleCatalog::PERMISSIONS)->sort()->values()->all());
});

test('an employee role account cannot open company-wide employee or organization pages', function () {
    $employee = tenantUserWithRole($this->registration, 'staff@roles.test', TenantRoleCatalog::EMPLOYEE);

    getAs($this, $employee, '/employees')->assertForbidden();
    getAs($this, $employee, '/organization')->assertForbidden();
    getAs($this, $employee, '/employee-document-types')->assertForbidden();
    getAs($this, $employee, '/dashboard')->assertOk();
});

test('a manager can view employees but cannot create them', function () {
    $manager = tenantUserWithRole($this->registration, 'boss@roles.test', TenantRoleCatalog::MANAGER);

    getAs($this, $manager, '/employees')->assertOk();

    $this->actingAs($manager, 'tenant')
        ->withSession(['tenant_session_version' => 1])
        ->post('http://' . $this->host . '/employees', ['first_name' => 'A', 'last_name' => 'B', 'employment_status' => 'draft'])
        ->assertForbidden();
});

test('an hr admin can manage employees but has no role management permission', function () {
    $hr = tenantUserWithRole($this->registration, 'hr@roles.test', TenantRoleCatalog::HR_ADMIN);

    getAs($this, $hr, '/employees')->assertOk();
    expect($this->registration['tenant']->run(fn () => $hr->can('roles.manage')))->toBeFalse();
});

test('another company owner cannot reach this company through their own login', function () {
    $other = app(RegisterCompanyAction::class)->execute(
        companyName: 'Other Co',
        adminName: 'Other Owner',
        email: 'owner@other.test',
        password: 'StrongPassword123!',
    );

    getAs($this, $other['user'], '/employees')->assertForbidden();
});

test('attendance dashboard and records need attendance.view', function () {
    $employee = tenantUserWithRole($this->registration, 'staff2@roles.test', TenantRoleCatalog::EMPLOYEE);
    $manager = tenantUserWithRole($this->registration, 'boss2@roles.test', TenantRoleCatalog::MANAGER);

    getAs($this, $employee, '/attendance')->assertForbidden();
    getAs($this, $employee, '/attendance/records')->assertForbidden();
    getAs($this, $manager, '/attendance')->assertOk();
    getAs($this, $manager, '/attendance/records')->assertOk();
});

test('an attendance mobile token from another company is rejected on this company domain', function () {
    $other = app(RegisterCompanyAction::class)->execute(
        companyName: 'Token Other Co',
        adminName: 'Other Owner',
        email: 'owner@tokenother.test',
        password: 'StrongPassword123!',
    );

    $token = $other['user']->createToken('Other Device', ['attendance:mobile'])->plainTextToken;

    $this->withToken($token)
        ->getJson('http://' . $this->host . '/api/v1/attendance/policy')
        ->assertStatus(403);
});

test('owner backfill promotes only the original registrant', function () {
    $tenant = $this->registration['tenant'];
    $registrant = $this->registration['user'];

    // Simulate the pre-roles state: no roles at all, plus later accounts.
    $tenant->run(fn () => $registrant->roles()->detach());
    $late = $tenant->run(function () use ($tenant) {
        $user = new User(['name' => 'Later account', 'email' => 'later@roles.test', 'password' => 'StrongPassword123!']);
        $user->forceFill(['tenant_id' => $tenant->id, 'account_type' => User::ACCOUNT_TYPE_TENANT, 'created_at' => now()->addDay()])->save();

        return $user;
    });

    $other = app(RegisterCompanyAction::class)->execute(
        companyName: 'Backfill Other Co',
        adminName: 'Other Owner',
        email: 'owner@backfillother.test',
        password: 'StrongPassword123!',
    );
    $other['tenant']->run(fn () => $other['user']->roles()->detach());

    $backfill = app(\App\Modules\Users\Actions\BackfillTenantOwnersAction::class);
    expect($backfill->execute($tenant))->toBe([]);
    expect($backfill->execute($other['tenant']))->toBe([]);

    $tenant->run(function () use ($registrant, $late) {
        expect($registrant->fresh()->hasRole(TenantRoleCatalog::OWNER))->toBeTrue();
        expect($late->fresh()->roles()->exists())->toBeFalse();
        expect($late->fresh()->can('employees.view'))->toBeFalse();
    });

    // Each company's owner belongs to that company only.
    $other['tenant']->run(function () use ($other, $registrant) {
        expect($other['user']->fresh()->hasRole(TenantRoleCatalog::OWNER))->toBeTrue();
        expect(User::query()->where('tenant_id', $other['tenant']->id)->where('id', $registrant->id)->exists())->toBeFalse();
    });
});

test('owner backfill leaves a company without owner when the registrant cannot be identified', function () {
    $tenant = $this->registration['tenant'];

    $tenant->run(fn () => $this->registration['user']->roles()->detach());
    User::query()->where('id', $this->registration['user']->id)->update(['created_at' => now()->addDays(3)]);

    $result = app(\App\Modules\Users\Actions\BackfillTenantOwnersAction::class)->execute($tenant);

    expect($result)->toBe([(string) $tenant->id]);
    $tenant->run(fn () => expect($this->registration['user']->fresh()->roles()->exists())->toBeFalse());
});
