<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Employees\Actions\CreateEmployeeAction;
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

    $this->reg = app(RegisterCompanyAction::class)->execute(
        companyName: 'Personal Data Co', adminName: 'PD Admin',
        email: 'pd-admin@tenant-test.example', password: 'StrongPassword123!',
    );
    $this->tid = (string) $this->reg['tenant']->id;
    $this->base = 'http://' . $this->reg['domain'];
    $this->emp = app(CreateEmployeeAction::class)->execute(
        data: ['first_name' => 'Target', 'last_name' => 'Person', 'employment_status' => 'active'],
        tenantId: $this->tid, actorId: null,
    );

    $this->mk = fn (string $role, string $email) => $this->reg['tenant']->run(function () use ($role, $email) {
        $u = new User(['name' => $email, 'email' => $email, 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole($role);

        return $u;
    });
    $this->as = fn (User $u) => $this->actingAs($u, 'tenant')->withSession(['tenant_session_version' => 1]);
});

test('personal data endpoints are HR only: managers and employee accounts get 403 on every method', function () {
    $id = $this->emp->id;
    $reads = ['addresses', 'emergency-contacts', 'identity-records', 'documents'];

    foreach ([TenantRoleCatalog::MANAGER => 'm@tenant-test.example', TenantRoleCatalog::EMPLOYEE => 'e@tenant-test.example'] as $role => $email) {
        $user = ($this->mk)($role, $email);

        foreach ($reads as $segment) {
            ($this->as)($user)->get("{$this->base}/employees/{$id}/{$segment}")->assertForbidden();
            ($this->as)($user)->post("{$this->base}/employees/{$id}/{$segment}", [])->assertForbidden();
        }
        ($this->as)($user)->get("{$this->base}/employees/{$id}/documents/1/download")->assertForbidden();
        ($this->as)($user)->get("{$this->base}/employees/{$id}/documents/1/preview")->assertForbidden();
        ($this->as)($user)->put("{$this->base}/employees/{$id}/addresses/1", [])->assertForbidden();
        ($this->as)($user)->delete("{$this->base}/employees/{$id}/emergency-contacts/1")->assertForbidden();
    }
});

test('HR admin and owner can reach personal data endpoints', function () {
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr@tenant-test.example');
    $id = $this->emp->id;

    foreach ([$hr, $this->reg['user']] as $user) {
        foreach (['addresses', 'emergency-contacts', 'identity-records'] as $segment) {
            ($this->as)($user)->get("{$this->base}/employees/{$id}/{$segment}")->assertOk();
        }
    }
});

test('managers can read the employee directory but cannot change it and employee accounts see nothing', function () {
    $manager = ($this->mk)(TenantRoleCatalog::MANAGER, 'm2@tenant-test.example');
    $staff = ($this->mk)(TenantRoleCatalog::EMPLOYEE, 'e2@tenant-test.example');

    ($this->as)($manager)->get("{$this->base}/employees")->assertOk();
    ($this->as)($manager)->post("{$this->base}/employees", ['first_name' => 'X', 'last_name' => 'Y'])->assertForbidden();
    ($this->as)($manager)->put("{$this->base}/employees/{$this->emp->id}", [])->assertForbidden();
    ($this->as)($manager)->delete("{$this->base}/employees/{$this->emp->id}")->assertForbidden();

    ($this->as)($staff)->get("{$this->base}/employees")->assertForbidden();
    ($this->as)($staff)->get("{$this->base}/employees/{$this->emp->id}")->assertForbidden();
    ($this->as)($staff)->get("{$this->base}/employees/{$this->emp->id}/profile-photo")->assertForbidden();
});

test('screens receive the signed-in permissions so denied actions are hidden', function () {
    $manager = ($this->mk)(TenantRoleCatalog::MANAGER, 'm3@tenant-test.example');
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr4@tenant-test.example');
    $url = "{$this->base}/employees/{$this->emp->id}";

    $can = function (User $user) use ($url) {
        $captured = [];
        ($this->as)($user)->get($url)->assertOk()->assertInertia(function ($page) use (&$captured) {
            $captured = $page->toArray()['props'];
        });

        return $captured;
    };

    $m = $can($manager);
    expect($m['canUpdate'])->toBeFalse()
        ->and($m['can']['employees.view'])->toBeTrue()
        ->and($m['can']['employees.manage'])->toBeFalse()
        ->and($m['can']['roles.manage'])->toBeFalse()
        ->and($m['can']['leave.approve'])->toBeTrue();

    $h = $can($hr);
    expect($h['canUpdate'])->toBeTrue()->and($h['can']['employees.manage'])->toBeTrue()->and($h['can']['roles.manage'])->toBeFalse();

    expect($can($this->reg['user'])['can']['roles.manage'])->toBeTrue();
});
