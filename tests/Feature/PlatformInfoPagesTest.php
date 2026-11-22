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

test('paid-until date is manual, logged, shown as a reminder, and never suspends anything', function () {
    app(PlatformAuthorizationSeeder::class)->run();
    app(PermissionRegistrar::class)->setPermissionsTeamId(PlatformTeam::ID);
    $this->admin->assignRole(Role::query()->where('tenant_id', PlatformTeam::ID)->where('name', 'Super Admin')->where('guard_name', 'platform')->firstOrFail());
    $tenant = \App\Models\Tenant::query()->firstOrFail();

    $this->actingAs($this->admin, 'platform')->patch(route('platform.companies.subscription', $tenant->getKey()), ['subscription_ends_at' => now()->addDays(5)->toDateString()])->assertSessionHasNoErrors();
    $this->actingAs($this->admin, 'platform')->get(route('platform.notifications.index'))
        ->assertInertia(fn ($p) => $p->where('items', fn ($i) => collect($i)->contains(fn ($x) => $x['kind'] === 'sub_expiring')));

    $this->actingAs($this->admin, 'platform')->patch(route('platform.companies.subscription', $tenant->getKey()), ['subscription_ends_at' => now()->subDays(3)->toDateString()]);
    $this->actingAs($this->admin, 'platform')->get(route('platform.notifications.index'))
        ->assertInertia(fn ($p) => $p->where('items', fn ($i) => collect($i)->contains(fn ($x) => $x['kind'] === 'sub_expired')));

    expect($tenant->fresh()->status)->toBe('active')
        ->and(\App\Models\PlatformActivityLog::where('event', 'company.subscription_changed')->count())->toBe(2);

    $this->actingAs($this->admin, 'platform')->patch(route('platform.companies.subscription', $tenant->getKey()), ['subscription_ends_at' => 'tomorrow'])->assertSessionHasErrors('subscription_ends_at');
    $this->actingAs($this->admin, 'platform')->get(route('platform.plans.index'))->assertInertia(fn ($p) => $p->where('companies.0.subscription_ends_at', now()->subDays(3)->toDateString()));
});

test('new companies get a 7-day trial date, existing ones do not, and an ended trial only reminds', function () {
    $new = \App\Models\Tenant::query()->firstOrFail();
    expect(substr((string) $new->getAttribute('trial_ends_at'), 0, 10))->toBe(now()->addDays(7)->toDateString());

    $legacy = \App\Models\Tenant::create(['company_name' => 'Legacy Co']);
    expect($legacy->fresh()->getAttribute('trial_ends_at'))->toBeNull();

    app(PlatformAuthorizationSeeder::class)->run();
    app(PermissionRegistrar::class)->setPermissionsTeamId(PlatformTeam::ID);
    $this->admin->assignRole(Role::query()->where('tenant_id', PlatformTeam::ID)->where('name', 'Super Admin')->where('guard_name', 'platform')->firstOrFail());
    $kinds = fn () => collect($this->actingAs($this->admin, 'platform')->get(route('platform.notifications.index'))->viewData('page')['props']['items'])->pluck('kind')->all();

    expect($kinds())->not->toContain('trial_ending', 'trial_ended');
    \App\Models\Tenant::query()->whereKey($new->getKey())->update(['trial_ends_at' => now()->addDays(2)->toDateString()]);
    expect($kinds())->toContain('trial_ending');
    \App\Models\Tenant::query()->whereKey($new->getKey())->update(['trial_ends_at' => now()->subDay()->toDateString()]);
    expect($kinds())->toContain('trial_ended');
    expect($new->fresh()->status)->toBe('active');
    \App\Models\Tenant::query()->whereKey($new->getKey())->update(['subscription_ends_at' => now()->addDays(30)->toDateString()]);
    expect($kinds())->not->toContain('trial_ended');
});
