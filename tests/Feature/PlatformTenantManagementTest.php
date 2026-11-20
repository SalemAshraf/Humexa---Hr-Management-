<?php

declare(strict_types=1);

use App\Models\PlatformActivityLog;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Models\Role;
use App\Modules\Users\Support\PlatformTeam;
use Database\Seeders\PlatformAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
    ]);

    $this->tenant = app(RegisterCompanyAction::class)->execute(
        companyName: 'Platform Managed Company',
        adminName: 'Tenant Admin',
        email: 'tenant-admin@platform-test.example',
        password: 'StrongPassword123!',
    )['tenant'];

    $this->platformUser = User::factory()->create();

    $this->platformUser->forceFill([
        'account_type' => User::ACCOUNT_TYPE_PLATFORM,
        'tenant_id' => null,
    ])->save();
});

function assignPlatformSuperAdmin(User $user): void
{
    app(PlatformAuthorizationSeeder::class)->run();

    app(PermissionRegistrar::class)->setPermissionsTeamId(PlatformTeam::ID);

    $role = Role::query()
        ->where('tenant_id', PlatformTeam::ID)
        ->where('name', 'Super Admin')
        ->where('guard_name', 'platform')
        ->firstOrFail();

    $user->assignRole($role);
}

test('platform account without explicit tenant permissions cannot access company management', function () {
    $this->actingAs($this->platformUser, 'platform')
        ->get(route('platform.companies.index'))
        ->assertForbidden();
});

test('super admin can view companies and suspend a company with an audit record', function () {
    assignPlatformSuperAdmin($this->platformUser);

    $this->actingAs($this->platformUser, 'platform')
        ->get(route('platform.companies.index'))
        ->assertOk();

    $this->actingAs($this->platformUser, 'platform')
        ->patch(route('platform.companies.suspend', $this->tenant), [
            'reason' => 'Overdue subscription',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tenants', [
        'id' => $this->tenant->id,
        'status' => 'suspended',
        'session_version' => 2,
    ]);

    $this->assertDatabaseHas('platform_activity_logs', [
        'actor_id' => $this->platformUser->id,
        'tenant_id' => $this->tenant->id,
        'event' => 'company.suspended',
        'old_status' => 'active',
        'new_status' => 'suspended',
        'reason' => 'Overdue subscription',
    ]);

    expect(PlatformActivityLog::query()->count())->toBe(1);
});

test('super admin can reactivate a company and audit the change', function () {
    assignPlatformSuperAdmin($this->platformUser);

    $this->tenant->forceFill([
        'status' => 'suspended',
        'session_version' => 2,
        'suspended_at' => now(),
    ])->save();

    $this->actingAs($this->platformUser, 'platform')
        ->patch(route('platform.companies.activate', $this->tenant), [
            'reason' => 'Payment confirmed',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tenants', [
        'id' => $this->tenant->id,
        'status' => 'active',
        'session_version' => 2,
        'suspended_at' => null,
    ]);

    $this->assertDatabaseHas('platform_activity_logs', [
        'actor_id' => $this->platformUser->id,
        'tenant_id' => $this->tenant->id,
        'event' => 'company.activated',
        'old_status' => 'suspended',
        'new_status' => 'active',
        'reason' => 'Payment confirmed',
    ]);
});

test('platform status endpoints reject invalid audit reasons', function () {
    assignPlatformSuperAdmin($this->platformUser);

    $this->actingAs($this->platformUser, 'platform')
        ->patch(route('platform.companies.suspend', $this->tenant), [
            'reason' => str_repeat('x', 1001),
        ])
        ->assertSessionHasErrors('reason');

    expect(PlatformActivityLog::query()->count())->toBe(0);
    expect(Tenant::query()->findOrFail($this->tenant->id)->status)->toBe('active');
});
