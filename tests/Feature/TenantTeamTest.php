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

test('accounts created by an owner are forced to change their starting password before anything else', function () {
    ($this->as)($this->reg['user'])->post("{$this->base}/team", ['name' => 'New HR', 'email' => 'newhr@tenant-test.example', 'password' => 'StartingPass123', 'role' => 'HR Admin'])
        ->assertSessionHasNoErrors();
    $new = User::where('email', 'newhr@tenant-test.example')->first();
    expect($new->must_change_password)->toBeTrue();

    foreach (['/dashboard', '/employees', '/leave', '/team'] as $path) {
        ($this->as)($new)->get($this->base . $path)->assertRedirect('/password/change');
    }
    ($this->as)($new)->get("{$this->base}/password/change")->assertOk();
    ($this->as)($new)->getJson("{$this->base}/employees")->assertForbidden();

    // Wrong current password, reuse and short passwords are rejected.
    ($this->as)($new)->post("{$this->base}/password/change", ['current_password' => 'nope', 'password' => 'BrandNewPass456!', 'password_confirmation' => 'BrandNewPass456!'])->assertSessionHasErrors('current_password');
    ($this->as)($new)->post("{$this->base}/password/change", ['current_password' => 'StartingPass123', 'password' => 'StartingPass123', 'password_confirmation' => 'StartingPass123'])->assertSessionHasErrors('password');
    ($this->as)($new)->post("{$this->base}/password/change", ['current_password' => 'StartingPass123', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    expect($new->fresh()->must_change_password)->toBeTrue();

    ($this->as)($new)->post("{$this->base}/password/change", ['current_password' => 'StartingPass123', 'password' => 'BrandNewPass456!', 'password_confirmation' => 'BrandNewPass456!'])
        ->assertRedirect('/dashboard');
    $fresh = $new->fresh();
    expect($fresh->must_change_password)->toBeFalse()->and(\Illuminate\Support\Facades\Hash::check('BrandNewPass456!', $fresh->password))->toBeTrue();

    ($this->as)($fresh)->get("{$this->base}/leave")->assertOk();
});

test('the company owner who registered is not forced to change password and mobile login waits for the change', function () {
    expect($this->reg['user']->fresh()->must_change_password)->toBeFalse();
    ($this->as)($this->reg['user'])->get("{$this->base}/dashboard")->assertOk();

    ($this->as)($this->reg['user'])->post("{$this->base}/team", ['name' => 'Field Staff', 'email' => 'field@tenant-test.example', 'password' => 'StartingPass123', 'role' => 'Employee']);
    $staff = User::where('email', 'field@tenant-test.example')->first();
    $emp = app(\App\Modules\Employees\Actions\CreateEmployeeAction::class)->execute(
        data: ['first_name' => 'Field', 'last_name' => 'Staff', 'employment_status' => 'active'], tenantId: $this->tid, actorId: null,
    );
    \App\Modules\Employees\Models\Employee::query()->whereKey($emp->id)->update(['user_id' => $staff->id]);

    $this->postJson("{$this->base}/api/v1/auth/login", ['email' => 'field@tenant-test.example', 'password' => 'StartingPass123', 'device_name' => 'phone'])
        ->assertForbidden();
});

test('owner deactivates and reactivates an account, and a deactivated account cannot get in', function () {
    $owner = $this->reg['user'];
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr-off@tenant-test.example');

    ($this->as)($owner)->put("{$this->base}/team/{$hr->id}/active", ['active' => false])->assertSessionHasNoErrors();
    expect($hr->fresh()->disabled_at)->not->toBeNull();

    // an already-open session is refused at the next request
    ($this->as)($hr->fresh())->get("{$this->base}/dashboard")->assertRedirect('/login');

    // a fresh password sign-in is refused
    $this->post("{$this->base}/login", ['email' => 'hr-off@tenant-test.example', 'password' => 'StrongPassword123!'])->assertSessionHasErrors('email');

    ($this->as)($owner)->put("{$this->base}/team/{$hr->id}/active", ['active' => true]);
    expect($hr->fresh()->disabled_at)->toBeNull();

    // guards: not yourself, not another company's account, not an HR admin
    ($this->as)($owner)->put("{$this->base}/team/{$owner->id}/active", ['active' => false])->assertSessionHasErrors('account');
    ($this->as)($hr)->put("{$this->base}/team/{$owner->id}/active", ['active' => false])->assertForbidden();
});

test('owner resets access: new password is forced to change and old app tokens end', function () {
    $owner = $this->reg['user'];
    $emp = ($this->mk)(TenantRoleCatalog::EMPLOYEE, 'emp-reset@tenant-test.example');
    $emp->createToken('phone', ['attendance:mobile']);

    ($this->as)($owner)->put("{$this->base}/team/{$emp->id}/reset-access", ['password' => 'short'])->assertSessionHasErrors('password');
    ($this->as)($owner)->put("{$this->base}/team/{$emp->id}/reset-access", ['password' => 'BrandNewStart123!'])->assertSessionHasNoErrors();

    $emp = $emp->fresh();
    expect($emp->must_change_password)->toBeTrue()
        ->and(\Illuminate\Support\Facades\Hash::check('BrandNewStart123!', $emp->password))->toBeTrue()
        ->and($emp->tokens()->count())->toBe(0);

    ($this->as)($owner)->put("{$this->base}/team/{$owner->id}/reset-access", ['password' => 'BrandNewStart123!'])->assertSessionHasErrors('password');
});
