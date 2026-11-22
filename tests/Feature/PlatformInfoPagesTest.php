<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Models\Role;
use App\Modules\Users\Support\PlatformTeam;
use Database\Seeders\PlatformAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => []]);
    app(RegisterCompanyAction::class)->execute(companyName: 'Info Co', adminName: 'A', email: 'a@info.example', password: 'StrongPassword123!');
    $this->admin = User::factory()->create();
    $this->admin->forceFill(['account_type' => User::ACCOUNT_TYPE_PLATFORM, 'tenant_id' => null])->save();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    auth()->guard('platform')->logout();
});

test('platform users, notifications and settings need platform permission', function () {
    foreach (['users', 'notifications', 'settings'] as $p) {
        $this->actingAs($this->admin, 'platform')->get(route("platform.$p.index"))->assertForbidden();
    }
    auth()->guard('platform')->logout();
    $this->refreshApplication();
    $this->get('/platform/users')->assertRedirect();
});

test('super admin sees the three pages with real data and they are read only', function () {
    app(PlatformAuthorizationSeeder::class)->run();
    app(PermissionRegistrar::class)->setPermissionsTeamId(PlatformTeam::ID);
    $this->admin->assignRole(Role::query()->where('tenant_id', PlatformTeam::ID)->where('name', 'Super Admin')->where('guard_name', 'platform')->firstOrFail());

    $this->actingAs($this->admin, 'platform')->get(route('platform.users.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/Users/Index')
            ->where('users.0.email', $this->admin->email)->where('users.0.roles.0', 'Super Admin')->where('users.0.is_you', true));

    $this->actingAs($this->admin, 'platform')->get(route('platform.notifications.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/Notifications/Index')
            ->where('items', fn ($items) => collect($items)->contains(fn ($i) => $i['kind'] === 'new' && $i['company'] === 'Info Co')));

    $this->actingAs($this->admin, 'platform')->get(route('platform.settings.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/Settings/Index')->where('info.companies', 1));

    $this->actingAs($this->admin, 'platform')->post('/platform/users')->assertStatus(405);
});
