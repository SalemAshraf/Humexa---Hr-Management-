<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Recruitment\Models\Candidate;
use App\Modules\Recruitment\Models\JobOpening;
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


test('attention items are scoped by role and counted live', function () {
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr-n@tenant-test.example');
    $mgr = ($this->mk)(TenantRoleCatalog::MANAGER, 'mgr-n@tenant-test.example');
    $emp = ($this->mk)(TenantRoleCatalog::EMPLOYEE, 'emp-n@tenant-test.example');
    $job = JobOpening::create(['tenant_id' => $this->tid, 'title' => 'T', 'employment_type' => 'full_time', 'positions' => 1, 'status' => 'open']);
    Candidate::create(['tenant_id' => $this->tid, 'job_opening_id' => $job->id, 'name' => 'Offer Person', 'stage' => 'offer']);
    Employee::query()->forceCreate(['tenant_id' => $this->tid, 'employee_number' => 'D-1', 'first_name' => 'Drafty', 'last_name' => 'Person', 'employment_status' => 'draft']);

    $keys = fn ($user) => collect(($this->as)($user)->get("{$this->base}/notifications")->assertOk()->viewData('page')['props']['items'])->pluck('key')->all();

    expect($keys($hr))->toContain('offers', 'drafts')
        ->and($keys($mgr))->not->toContain('offers', 'drafts');
    ($this->as)($emp)->get("{$this->base}/notifications")->assertOk();
    expect($keys($emp))->toBe([]);

    ($this->as)($hr)->get("{$this->base}/dashboard")->assertInertia(fn ($p) => $p->where('attention_count', 2));
});

test('search only returns what the role may open and escapes wildcards', function () {
    $hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr-s@tenant-test.example');
    $mgr = ($this->mk)(TenantRoleCatalog::MANAGER, 'mgr-s@tenant-test.example');
    $emp = ($this->mk)(TenantRoleCatalog::EMPLOYEE, 'emp-s@tenant-test.example');
    $job = JobOpening::create(['tenant_id' => $this->tid, 'title' => 'Welder', 'employment_type' => 'full_time', 'positions' => 1, 'status' => 'open']);
    Candidate::create(['tenant_id' => $this->tid, 'job_opening_id' => $job->id, 'name' => 'Welda Candidate', 'stage' => 'applied']);
    Employee::query()->forceCreate(['tenant_id' => $this->tid, 'employee_number' => 'S-1', 'first_name' => 'Weldon', 'last_name' => 'Staff', 'employment_status' => 'active']);

    $labels = fn ($user, $q) => collect(($this->as)($user)->get("{$this->base}/search?q=".urlencode($q))->assertOk()->viewData('page')['props']['groups'])->pluck('label')->all();

    expect($labels($hr, 'weld'))->toContain('Employees', 'Candidates', 'Job openings')
        ->and($labels($mgr, 'weld'))->toBe(['Employees'])
        ->and($labels($emp, 'weld'))->toBe([])
        ->and($labels($hr, '%%'))->toBe([])
        ->and($labels($hr, 'w'))->toBe([]);
});
