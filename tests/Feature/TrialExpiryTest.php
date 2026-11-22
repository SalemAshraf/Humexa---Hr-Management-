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
    $this->reg = app(RegisterCompanyAction::class)->execute(companyName: 'Trial Co', adminName: 'Owner', email: 'trial-owner@tenant-test.example', password: 'StrongPassword123!');
    $this->tenant = $this->reg['tenant'];
    $this->base = 'http://' . $this->reg['domain'];
    $tid = (string) $this->tenant->id;
    $this->owner = $this->tenant->run(fn () => User::where('email', 'trial-owner@tenant-test.example')->first());
    $this->as = fn () => $this->actingAs($this->owner, 'tenant')->withSession(['tenant_session_version' => 1]);
    $this->emp = ['first_name' => 'New', 'last_name' => 'Person', 'employment_status' => 'active', 'hire_date' => '2026-01-01'];
});

test('new company has a 7 day trial and can write while it runs', function () {
    expect($this->tenant->fresh()->trial_ends_at)->not->toBeNull();
    ($this->as)()->post("{$this->base}/employees", $this->emp)->assertSessionHasNoErrors();
});

test('after the trial ends writes are blocked, reads still work, activation lifts the block', function () {
    $this->tenant->forceFill(['trial_ends_at' => now()->subDays(2)->toDateString()])->save();

    ($this->as)()->get("{$this->base}/employees")->assertOk();
    ($this->as)()->post("{$this->base}/employees", $this->emp)->assertSessionHasErrors('trial');
    ($this->as)()->postJson("{$this->base}/employees", $this->emp)->assertForbidden();
    ($this->as)()->post("{$this->base}/logout")->assertSessionHasNoErrors();

    $this->tenant->forceFill(['subscription_ends_at' => now()->addMonth()->toDateString()])->save();
    $this->flushSession();
    tenancy()->end();
    ($this->as)()->post("{$this->base}/employees", $this->emp)->assertSessionHasNoErrors();
});

test('companies without a trial date are never locked', function () {
    $this->tenant->forceFill(['trial_ends_at' => null])->save();
    ($this->as)()->post("{$this->base}/employees", $this->emp)->assertSessionHasNoErrors();
});

test('the trial runs through its last day on the company calendar, and the mobile API writes are blocked after it', function () {
    $this->tenant->forceFill(['trial_ends_at' => \App\Modules\Settings\Support\CompanyClock::today((string) $this->tenant->id)])->save();
    ($this->as)()->post("{$this->base}/employees", $this->emp)->assertSessionHasNoErrors();

    tenancy()->end();
    $this->flushSession();
    $this->tenant->forceFill(['trial_ends_at' => now()->subDays(2)->toDateString()])->save();
    $this->postJson("{$this->base}/api/v1/attendance/check-in", [])->assertStatus(401)->assertJsonMissing(['message' => 'x']);
    \Laravel\Sanctum\Sanctum::actingAs($this->owner, ['attendance:mobile']);
    $this->postJson("{$this->base}/api/v1/attendance/check-in", [])->assertForbidden()->assertJsonPath('message', fn ($m) => str_contains($m, 'free trial has ended'));
});
