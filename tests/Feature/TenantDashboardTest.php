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

test('dashboard analytics count only this company and respect permissions', function () {
    $other = app(RegisterCompanyAction::class)->execute(
        companyName: 'Analytics Other Co',
        adminName: 'Other Admin',
        email: 'analytics-other@tenant-test.example',
        password: 'StrongPassword123!',
    );

    $make = fn (array $reg, string $first, string $status = 'active') => app(\App\Modules\Employees\Actions\CreateEmployeeAction::class)->execute(
        data: ['first_name' => $first, 'last_name' => 'Person', 'employment_status' => $status, 'hire_date' => now()->toDateString()],
        tenantId: (string) $reg['tenant']->id,
        actorId: null,
    );

    $make($this->registration, 'One');
    $make($this->registration, 'Two', 'probation');
    $make($this->registration, 'Gone', 'resigned');
    $make($other, 'Foreign');
    $make($other, 'Foreign2');

    $employee = $this->tenant->run(function () {
        $user = new User(['name' => 'Plain Staff', 'email' => 'plain@tenant-test.example', 'password' => 'StrongPassword123!']);
        $user->forceFill(['tenant_id' => $this->tenant->id, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $user->assignRole(\App\Modules\Users\Support\TenantRoleCatalog::EMPLOYEE);

        return $user;
    });

    $url = 'http://' . $this->registration['domain'] . '/dashboard';

    $this->actingAs($this->admin, 'tenant')
        ->withSession(['tenant_session_version' => 1])
        ->get($url)
        ->assertInertia(fn ($page) => $page
            ->where('analytics.headcount.current', 2)
            ->where('analytics.headcount.on_probation', 1)
            ->where('analytics.attendance.expected', 2)
            ->where('analytics.leave.pending', 0)
        );

    $this->actingAs($employee, 'tenant')
        ->withSession(['tenant_session_version' => 1])
        ->get($url)
        ->assertRedirect('/me'); // no company analytics payload is built for a self-service account
});
