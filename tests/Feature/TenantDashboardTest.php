<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
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
        companyName: 'Dashboard Test Company',
        adminName: 'Dashboard Admin',
        email: 'dashboard-admin@tenant-test.example',
        password: 'StrongPassword123!',
    );

    $this->tenant = $this->registration['tenant'];
    $this->admin = $this->registration['user'];
});

test('tenant administrator can view dashboard with only current company data', function () {
    $this->actingAs($this->admin, 'tenant')
        ->withSession(['tenant_session_version' => 1])
        ->get('http://' . $this->registration['domain'] . '/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Company/Dashboard')
            ->where('company.name', 'Dashboard Test Company')
            ->where('company.domain', $this->registration['domain'])
            ->where('company.status', 'active')
            ->where('stats.teamAccounts', 1)
        );
});

test('a user belonging to another tenant cannot access this company dashboard', function () {
    $otherRegistration = app(RegisterCompanyAction::class)->execute(
        companyName: 'Another Dashboard Company',
        adminName: 'Other Admin',
        email: 'other-dashboard-admin@tenant-test.example',
        password: 'StrongPassword123!',
    );

    $this->actingAs($otherRegistration['user'], 'tenant')
        ->withSession(['tenant_session_version' => 1])
        ->get('http://' . $this->registration['domain'] . '/dashboard')
        ->assertForbidden();
});
