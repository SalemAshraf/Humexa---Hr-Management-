<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);

    $this->reg = app(RegisterCompanyAction::class)->execute(
        companyName: 'Team Co', adminName: 'Team Owner', email: 'team-owner@tenant-test.example', password: 'StrongPassword123!',
    );
    $this->tid = (string) $this->reg['tenant']->id;
    $this->base = 'http://' . $this->reg['domain'];
    $this->mk = fn (string $role, string $email) => $this->reg['tenant']->run(function () use ($role, $email) {
        $u = new User(['name' => $email, 'email' => $email, 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole($role);

        return $u;
    });
    $this->as = fn (User $u) => $this->actingAs($u, 'tenant')->withSession(['tenant_session_version' => 1]);
    $this->roleOf = fn (User $u) => $this->reg['tenant']->run(fn () => $u->fresh()->getRoleNames()->all());
});

test('owner adds accounts and changes roles; only one role per account', function () {
    $owner = $this->reg['user'];

    ($this->as)($owner)->post("{$this->base}/team", ['name' => 'Hana HR', 'email' => 'hana@tenant-test.example', 'password' => 'LongEnoughPass1!', 'role' => 'HR Admin'])
        ->assertSessionHasNoErrors();
    $hana = User::where('email', 'hana@tenant-test.example')->first();
    expect($hana->tenant_id)->toBe($this->tid)->and(($this->roleOf)($hana))->toBe(['HR Admin']);

    ($this->as)($owner)->put("{$this->base}/team/{$hana->id}/role", ['role' => 'Manager'])->assertSessionHasNoErrors();
    expect(($this->roleOf)($hana))->toBe(['Manager']);

    ($this->as)($owner)->post("{$this->base}/team", ['name' => 'Weak', 'email' => 'weak@tenant-test.example', 'password' => 'short', 'role' => 'Employee'])
        ->assertSessionHasErrors('password');
    ($this->as)($owner)->post("{$this->base}/team", ['name' => 'Bad', 'email' => 'bad@tenant-test.example', 'password' => 'LongEnoughPass1!', 'role' => 'Superuser'])
        ->assertSessionHasErrors('role');
});

test('nobody but an owner can open or change the team, and HR admins cannot grant Owner', function () {
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr3@tenant-test.example');
    $mgr = ($this->mk)(TenantRoleCatalog::MANAGER, 'mgr3@tenant-test.example');
    $victim = ($this->mk)(TenantRoleCatalog::EMPLOYEE, 'v@tenant-test.example');

    foreach ([$hr, $mgr, $victim] as $u) {
        ($this->as)($u)->get("{$this->base}/team")->assertForbidden();
        ($this->as)($u)->put("{$this->base}/team/{$victim->id}/role", ['role' => 'Owner'])->assertForbidden();
        ($this->as)($u)->post("{$this->base}/team", ['name' => 'X', 'email' => 'x@tenant-test.example', 'password' => 'LongEnoughPass1!', 'role' => 'Owner'])->assertForbidden();
    }
    expect(($this->roleOf)($victim))->toBe(['Employee']);
});

test('owner cannot demote themselves or the last owner, and cannot touch another company', function () {
    $owner = $this->reg['user'];
    ($this->as)($owner)->put("{$this->base}/team/{$owner->id}/role", ['role' => 'Employee'])->assertSessionHasErrors('role');
    expect(($this->roleOf)($owner))->toBe(['Owner']);

    $second = ($this->mk)(TenantRoleCatalog::OWNER, 'owner2@tenant-test.example');
    ($this->as)($owner)->put("{$this->base}/team/{$second->id}/role", ['role' => 'Manager'])->assertSessionHasNoErrors(); // two owners: allowed
    expect(($this->roleOf)($second))->toBe(['Manager']);

    $other = app(RegisterCompanyAction::class)->execute(
        companyName: 'Other Team Co', adminName: 'Other Owner', email: 'other-team@tenant-test.example', password: 'StrongPassword123!',
    );
    ($this->as)($owner)->put("{$this->base}/team/{$other['user']->id}/role", ['role' => 'Employee'])->assertNotFound();
    expect($other['tenant']->run(fn () => $other['user']->fresh()->getRoleNames()->all()))->toBe(['Owner']);

    ($this->as)($owner)->get("{$this->base}/team")->assertOk()
        ->assertInertia(fn ($p) => $p->has('accounts', 2));
});
