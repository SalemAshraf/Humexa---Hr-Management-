<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Settings\Models\CompanySetting;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);
    $this->reg = app(RegisterCompanyAction::class)->execute(companyName: 'Set Co', adminName: 'Set Owner', email: 'set-owner@tenant-test.example', password: 'StrongPassword123!');
    $this->tid = (string) $this->reg['tenant']->id;
    $this->base = 'http://'.$this->reg['domain'];
    $this->mk = fn (string $role, string $email) => $this->reg['tenant']->run(function () use ($role, $email) {
        $u = new User(['name' => $email, 'email' => $email, 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole($role);

        return $u;
    });
    $this->as = fn (User $u) => $this->actingAs($u, 'tenant')->withSession(['tenant_session_version' => 1]);
});

test('hr saves settings, others are refused, bad values are rejected', function () {
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr-set@tenant-test.example');
    $mgr = ($this->mk)(TenantRoleCatalog::MANAGER, 'mgr-set@tenant-test.example');
    $ok = ['display_name' => 'Nile Co', 'timezone' => 'Africa/Cairo', 'currency' => 'egp', 'work_start' => '08:30', 'work_end' => '16:30', 'contact_email' => 'hr@nile.example', 'contact_phone' => '0100'];

    $r = ($this->as)($hr)->get("{$this->base}/settings")->assertOk();
    expect($r->original->getData()['page']['props']['settings']['display_name'])->toBe('Set Co');
    ($this->as)($mgr)->get("{$this->base}/settings")->assertForbidden();
    ($this->as)($mgr)->put("{$this->base}/settings", $ok)->assertForbidden();

    ($this->as)($hr)->put("{$this->base}/settings", ['timezone' => 'Mars/Base'] + $ok)->assertSessionHasErrors('timezone');
    ($this->as)($hr)->put("{$this->base}/settings", ['work_end' => '08:00'] + $ok)->assertSessionHasErrors('work_end');
    ($this->as)($hr)->put("{$this->base}/settings", $ok)->assertRedirect();

    $row = CompanySetting::where('tenant_id', $this->tid)->firstOrFail();
    expect($row->currency)->toBe('EGP')->and($row->display_name)->toBe('Nile Co');
});
