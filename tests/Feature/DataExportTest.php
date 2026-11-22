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
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);
    $this->a = app(RegisterCompanyAction::class)->execute(companyName: 'Export A', adminName: 'A Owner', email: 'exp-a@tenant-test.example', password: 'StrongPassword123!');
    $this->b = app(RegisterCompanyAction::class)->execute(companyName: 'Export B', adminName: 'B Owner', email: 'exp-b@tenant-test.example', password: 'StrongPassword123!');
    $this->aid = (string) $this->a['tenant']->id;
    $this->bid = (string) $this->b['tenant']->id;
    $this->base = 'http://'.$this->a['domain'];
    app(CreateEmployeeAction::class)->execute(data: ['first_name' => '=HYPERLINK("x")', 'last_name' => 'Alpha', 'employment_status' => 'active', 'personal_email' => 'private-mail@home.example'], tenantId: $this->aid, actorId: null);
    app(CreateEmployeeAction::class)->execute(data: ['first_name' => 'Zed', 'last_name' => 'OtherCompany', 'employment_status' => 'active'], tenantId: $this->bid, actorId: null);
    $this->mk = fn (string $role, string $email) => $this->a['tenant']->run(function () use ($role, $email) {
        $u = new User(['name' => $email, 'email' => $email, 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $this->aid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole($role);

        return $u;
    });
    $this->as = fn (User $u) => $this->actingAs($u, 'tenant')->withSession(['tenant_session_version' => 1]);
});

test('HR can export, the file is company-scoped, formula-safe and free of sensitive fields', function () {
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr-exp@tenant-test.example');
    foreach (['employees', 'leave', 'attendance'] as $e) {
        ($this->as)($hr)->get("{$this->base}/data-export/{$e}")->assertOk()->assertDownload();
    }
    $csv = ($this->as)($hr)->get("{$this->base}/data-export/employees")->streamedContent();
    expect($csv)->toContain("'=HYPERLINK")->and($csv)->not->toContain('OtherCompany')->and($csv)->not->toContain('private-mail@home.example')
        ->and($csv)->not->toContain(',=HYPERLINK');
});

test('managers, employees and guests cannot export, and unknown entities 404', function () {
    foreach ([TenantRoleCatalog::MANAGER => 'm-exp@tenant-test.example', TenantRoleCatalog::EMPLOYEE => 'e-exp@tenant-test.example'] as $role => $email) {
        $u = ($this->mk)($role, $email);
        foreach (['employees', 'leave', 'attendance'] as $e) {
            ($this->as)($u)->get("{$this->base}/data-export/{$e}")->assertForbidden();
        }
    }
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr2-exp@tenant-test.example');
    ($this->as)($hr)->get("{$this->base}/data-export/payroll")->assertNotFound();
    ($this->as)($hr)->get("{$this->base}/data-export/..%2Fusers")->assertNotFound();
    auth('tenant')->logout();
    $this->get("{$this->base}/data-export/employees")->assertRedirect();
});

test('a large export is never silently cut: every row of the company is in the file', function () {
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr3-exp@tenant-test.example');
    $now = now()->toDateTimeString();
    $rows = [];
    for ($i = 1; $i <= 2300; $i++) {
        $rows[] = ['tenant_id' => $this->aid, 'employee_number' => "BULK-{$i}", 'first_name' => "Bulk{$i}", 'last_name' => 'Row', 'employment_status' => 'active', 'created_at' => $now, 'updated_at' => $now];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        \App\Modules\Employees\Models\Employee::query()->insert($chunk);
    }
    $csv = ($this->as)($hr)->get("{$this->base}/data-export/employees")->streamedContent();
    expect(substr_count($csv, "\n"))->toBe(2300 + 1 + 1 + 0); // header + 2300 bulk + the one seeded employee
    expect($csv)->toContain('BULK-2300')->and($csv)->toContain('BULK-1,');
});
