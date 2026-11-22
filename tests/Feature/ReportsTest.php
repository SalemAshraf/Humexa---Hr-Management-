<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);
    $this->reg = app(RegisterCompanyAction::class)->execute(companyName: 'Rep Co', adminName: 'Rep Owner', email: 'rep-owner@tenant-test.example', password: 'StrongPassword123!');
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

function repEmp($t, string $first, ?int $managerId = null, ?int $userId = null): Employee
{
    return Employee::query()->forceCreate(['tenant_id' => $t->tid, 'employee_number' => 'R-'.$first, 'first_name' => $first, 'last_name' => 'T', 'employment_status' => 'active', 'hire_date' => '2024-01-01', 'manager_id' => $managerId, 'user_id' => $userId]);
}

test('hr sees the whole company, a manager only direct reports, an employee nothing', function () {
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr-rep@tenant-test.example');
    $mgr = ($this->mk)(TenantRoleCatalog::MANAGER, 'mgr-rep@tenant-test.example');
    $emp = ($this->mk)(TenantRoleCatalog::EMPLOYEE, 'emp-rep@tenant-test.example');
    $boss = repEmp($this, 'Boss', null, $mgr->id);
    repEmp($this, 'Mine', $boss->id);
    repEmp($this, 'Other');

    $count = fn ($r) => collect($r->viewData('page')['props']['sections']['Headcount'] ?? [])->sum('value');
    $page = fn ($r) => $r->original->getData()['page']['props'];

    $r = ($this->as)($hr)->get("{$this->base}/reports")->assertOk();
    expect(collect($page($r)['sections']['Headcount'])->sum('value'))->toBe(3)->and($page($r)['scope'])->toBe('company')
        ->and($page($r)['sections'])->toHaveKeys(['Recruitment pipeline', 'Payroll runs, net total (EGP)']);

    $r = ($this->as)($mgr)->get("{$this->base}/reports")->assertOk();
    expect(collect($page($r)['sections']['Headcount'])->sum('value'))->toBe(1)->and($page($r)['scope'])->toBe('direct_reports')
        ->and($page($r)['sections'])->not->toHaveKeys(['Recruitment pipeline', 'Payroll runs, net total (EGP)']);

    // department rows add up to everyone who is not resigned, terminated or draft
    $r = ($this->as)($hr)->get("{$this->base}/reports")->assertOk();
    expect(collect($page($r)['sections']['Current headcount by department'])->sum('value'))->toBe(3);
    repEmp($this, 'Gone')->forceFill(['employment_status' => 'resigned'])->save();
    $r = ($this->as)($hr)->get("{$this->base}/reports")->assertOk();
    expect(collect($page($r)['sections']['Headcount'])->sum('value'))->toBe(4)
        ->and(collect($page($r)['sections']['Current headcount by department'])->sum('value'))->toBe(3);

    ($this->as)($emp)->get("{$this->base}/reports")->assertForbidden();
    ($this->as)($emp)->get("{$this->base}/reports/export")->assertForbidden();
    ($this->as)($hr)->get("{$this->base}/reports/export")->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    // the manager's CSV carries the same direct-report scope and none of the HR-only sections
    $mgrCsv = ($this->as)($mgr)->get("{$this->base}/reports/export")->assertOk()->streamedContent();
    expect($mgrCsv)->toContain('Direct reports only')->and($mgrCsv)->not->toContain('Whole company')->and($mgrCsv)->not->toContain('Payroll runs')->and($mgrCsv)->not->toContain('Recruitment pipeline');
    ($this->as)($hr)->get("{$this->base}/reports?from=2020-01-01&to=2026-01-01")->assertStatus(422);
});

test('approved leave only counts working days that fall inside the period', function () {
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr-rep2@tenant-test.example');
    $e = repEmp($this, 'Leaver');
    $type = \App\Modules\Leave\Models\LeaveType::query()->forceCreate(['tenant_id' => $this->tid, 'name' => 'Annual', 'annual_days' => 21, 'is_paid' => true]);
    // 2026-03-01 (Sun) to 2026-03-12 (Thu): 10 working days with a Fri/Sat weekend. Period covers 2026-03-08 to 2026-03-12 = 5.
    \App\Modules\Leave\Models\LeaveRequest::query()->forceCreate(['tenant_id' => $this->tid, 'employee_id' => $e->id, 'leave_type_id' => $type->id, 'start_date' => '2026-03-01', 'end_date' => '2026-03-12', 'days' => 10, 'status' => 'approved']);

    $r = ($this->as)($hr)->get("{$this->base}/reports?from=2026-03-08&to=2026-03-31")->assertOk();
    $rows = collect($r->original->getData()['page']['props']['sections']['Approved leave, working days inside the period']);
    expect((float) $rows->firstWhere('label', 'Annual')['value'])->toBe(5.0);
});
