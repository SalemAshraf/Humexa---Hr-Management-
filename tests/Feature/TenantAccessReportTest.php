<?php

declare(strict_types=1);

use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('access report is read-only, resolves an exact domain and fails closed', function () {
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => []]);
    $reg = app(RegisterCompanyAction::class)->execute(
        companyName: 'Report Co', adminName: 'Report Owner', email: 'owner@report.example', password: 'StrongPassword123!',
    );

    $this->artisan('app:tenant-access', ['domain' => $reg['domain']])
        ->expectsOutputToContain((string) $reg['tenant']->id)
        ->expectsOutputToContain('owner@report.example')
        ->assertSuccessful();

    $this->artisan('app:tenant-access', ['domain' => 'report']) // partial, not exact
        ->assertFailed();
    $this->artisan('app:tenant-access', ['domain' => 'nope.humexa.test'])->assertFailed();
});
