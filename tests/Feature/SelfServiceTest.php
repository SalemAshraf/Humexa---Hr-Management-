<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Employees\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);

    $this->reg = app(RegisterCompanyAction::class)->execute(companyName: 'Me Co', adminName: 'Me Owner', email: 'me-owner@tenant-test.example', password: 'StrongPassword123!');
    $this->tid = (string) $this->reg['tenant']->id;
    $this->base = 'http://' . $this->reg['domain'];
    $this->as = fn (User $u) => $this->actingAs($u, 'tenant')->withSession(['tenant_session_version' => 1]);

    $this->account = function (string $email, ?string $first = null) {
        $u = $this->reg['tenant']->run(function () use ($email) {
            $u = new User(['name' => $email, 'email' => $email, 'password' => 'StrongPassword123!']);
            $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
            $u->assignRole(TenantRoleCatalog::EMPLOYEE);

            return $u;
        });
        $emp = null;
        if ($first) {
            $emp = app(CreateEmployeeAction::class)->execute(data: ['first_name' => $first, 'last_name' => 'Self', 'employment_status' => 'active', 'hire_date' => '2026-01-05', 'user_id' => $u->id], tenantId: $this->tid, actorId: null);
        }

        return [$u, $emp];
    };

    $this->annual = LeaveType::create(['tenant_id' => $this->tid, 'name' => 'Annual', 'annual_days' => 10, 'is_paid' => true, 'is_active' => true]);
});

test('an unlinked employee account sees an empty state and cannot request leave', function () {
    [$u] = ($this->account)('nolink@tenant-test.example');

    ($this->as)($u)->get("{$this->base}/me")->assertOk()->assertInertia(fn ($p) => $p->where('linked', false));
    ($this->as)($u)->post("{$this->base}/me/leave", ['leave_type_id' => $this->annual->id, 'start_date' => '2026-11-22', 'end_date' => '2026-11-22'])->assertForbidden();
});

test('a linked employee sees only their own data and can request and withdraw leave', function () {
    [$u, $emp] = ($this->account)('ann@tenant-test.example', 'Ann');
    [, $other] = ($this->account)('bob@tenant-test.example', 'Bob');

    ($this->as)($u)->post("{$this->base}/me/leave", ['leave_type_id' => $this->annual->id, 'start_date' => '2026-11-22', 'end_date' => '2026-11-23'])->assertSessionHasNoErrors();
    $req = LeaveRequest::where('employee_id', $emp->id)->firstOrFail();
    expect($req->status)->toBe('pending');

    // overlap is still enforced
    ($this->as)($u)->post("{$this->base}/me/leave", ['leave_type_id' => $this->annual->id, 'start_date' => '2026-11-23', 'end_date' => '2026-11-24'])->assertSessionHasErrors();

    // someone else's request cannot be withdrawn, and they cannot pass another employee id
    [$bobUser] = [User::where('email', 'bob@tenant-test.example')->first()];
    ($this->as)($bobUser)->post("{$this->base}/me/leave/{$req->id}/cancel")->assertNotFound();
    ($this->as)($bobUser)->post("{$this->base}/me/leave", ['employee_id' => $emp->id, 'leave_type_id' => $this->annual->id, 'start_date' => '2026-12-06', 'end_date' => '2026-12-06'])->assertSessionHasNoErrors();
    expect(LeaveRequest::where('employee_id', $emp->id)->count())->toBe(1)->and(LeaveRequest::where('employee_id', $other->id)->count())->toBe(1);

    ($this->as)($u)->get("{$this->base}/me")->assertInertia(fn ($p) => $p->where('profile.name', $emp->full_name)->has('requests', 1));

    ($this->as)($u)->post("{$this->base}/me/leave/{$req->id}/cancel")->assertSessionHasNoErrors();
    expect($req->fresh()->status)->toBe('cancelled');
});

test('payslip estimates show only for the signed-in employee and only from locked runs', function () {
    [$u, $emp] = ($this->account)('pay@tenant-test.example', 'Pay');
    [, $other] = ($this->account)('pay2@tenant-test.example', 'Other');

    $rules = \App\Modules\Payroll\Models\PayrollRuleSet::query()->first() ?? null;
    $mk = function (int $month, string $status) use ($emp, $other) {
        $run = PayrollRun::query()->create(['tenant_id' => $this->tid, 'year' => 2026, 'month' => $month, 'rule_set_id' => 0, 'status' => $status]);
        foreach ([$emp, $other] as $e) {
            Payslip::query()->create(['tenant_id' => $this->tid, 'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'gross' => 1000 + $e->id, 'net_pay' => 900 + $e->id]);
        }
    };

    try {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        $mk(9, 'draft');
        $mk(10, 'approved');
    } finally {
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
    }

    ($this->as)($u)->get("{$this->base}/me")->assertInertia(fn ($p) => $p->has('payslips', 1)->where('payslips.0.period', '2026-10')->where('payslips.0.gross', fn ($v) => (float) $v === (float) (1000 + $emp->id)));
});

test('a user from another company cannot reach this company workspace self-service', function () {
    $other = app(RegisterCompanyAction::class)->execute(companyName: 'Other Co', adminName: 'O', email: 'o-owner@tenant-test.example', password: 'StrongPassword123!');
    $this->actingAs($other['user'], 'tenant')->withSession(['tenant_session_version' => 1])->get("{$this->base}/me")->assertForbidden();
});

test('an employee account lands on My space and cannot open company modules', function () {
    [$u] = ($this->account)('land@tenant-test.example', 'Land');

    ($this->as)($u)->get("{$this->base}/dashboard")->assertRedirect('/me');
    foreach (['/employees', '/leave', '/attendance', '/payroll', '/reports', '/settings', '/team', '/recruitment', '/performance', '/organization', '/attendance/requests'] as $path) {
        ($this->as)($u)->get($this->base . $path)->assertForbidden();
    }
    ($this->as)($u)->get("{$this->base}/notifications")->assertOk();
});

test('a manager sees attendance only for direct reports, HR sees everyone, and a stranger gets nothing', function () {
    $mgrUser = $this->reg['tenant']->run(function () {
        $u = new User(['name' => 'm', 'email' => 'mgr-att@tenant-test.example', 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole(TenantRoleCatalog::MANAGER);

        return $u;
    });
    $act = fn (array $d) => app(CreateEmployeeAction::class)->execute(data: $d + ['last_name' => 'Att', 'employment_status' => 'active', 'hire_date' => '2026-01-05'], tenantId: $this->tid, actorId: null);
    $boss = $act(['first_name' => 'Boss', 'user_id' => $mgrUser->id]);
    $report = $act(['first_name' => 'Report', 'manager_id' => $boss->id]);
    $stranger = $act(['first_name' => 'Stranger']);
    foreach ([$report, $stranger] as $e) {
        \App\Modules\Attendance\Models\AttendanceRecord::create(['tenant_id' => $this->tid, 'employee_id' => $e->id, 'attendance_date' => now()->toDateString(), 'status' => 'present']);
    }

    ($this->as)($mgrUser)->get("{$this->base}/attendance/records")->assertInertia(fn ($p) => $p->has('records.data', 1)->where('records.data.0.employee.id', $report->id));
    ($this->as)($mgrUser)->get("{$this->base}/attendance")->assertInertia(fn ($p) => $p->where('stats.total_employees', 1)->where('stats.with_records', 1));
    ($this->as)($this->reg['user'])->get("{$this->base}/attendance/records")->assertInertia(fn ($p) => $p->has('records.data', 2));
    ($this->as)($mgrUser)->get("{$this->base}/attendance/requests")->assertForbidden();
});

test('route audit: a permissionless account is refused on every company route except its own space', function () {
    [$u] = ($this->account)('audit@tenant-test.example', 'Audit');
    $allowed = ['/', 'login', 'logout', 'password/change', 'dashboard', 'me', 'me/leave', 'me/leave/{leaveRequest}/cancel', 'notifications', 'search'];

    $checked = 0;
    $leaks = [];
    foreach (app('router')->getRoutes() as $route) {
        $domain = (string) $route->getDomain();
        $uri = $route->uri();
        if (! str_contains($domain, '{tenant}') || str_starts_with($uri, 'api/') || str_contains($uri, 'v1/')) {
            continue;
        }
        $path = '/' . ltrim($uri, '/');
        $key = trim($path, '/') ?: '/';
        if (in_array($key, $allowed, true)) {
            continue;
        }
        $url = $this->base . preg_replace('/\{[^}]+\}/', '1', $path);
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $res = ($this->as)($u)->call($method, $url);
            $checked++;
            if (! in_array($res->getStatusCode(), [302, 401, 403, 404, 405, 419], true) || ($res->getStatusCode() === 302 && ! str_contains((string) $res->headers->get('Location'), '/login') && ! str_contains((string) $res->headers->get('Location'), '/me'))) {
                $leaks[] = "$method $path => " . $res->getStatusCode();
            }
        }
    }

    expect($checked)->toBeGreaterThan(60)->and($leaks)->toBe([]);
});

test('route audit: a manager is refused on every route whose permission they do not hold', function () {
    $mgr = $this->reg['tenant']->run(function () {
        $u = new User(['name' => 'm', 'email' => 'mgr-audit@tenant-test.example', 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole(TenantRoleCatalog::MANAGER);

        return $u;
    });
    $held = TenantRoleCatalog::roles()[TenantRoleCatalog::MANAGER];

    $checked = 0;
    $leaks = [];
    foreach (app('router')->getRoutes() as $route) {
        if (! str_contains((string) $route->getDomain(), '{tenant}') || str_contains($route->uri(), 'v1/')) {
            continue;
        }
        $needsMissing = false;
        foreach ($route->gatherMiddleware() as $mw) {
            if (is_string($mw) && str_starts_with($mw, 'tenant.can:')) {
                $perms = explode(',', substr($mw, strlen('tenant.can:')));
                if (array_intersect($perms, $held) === []) {
                    $needsMissing = true;
                }
            }
        }
        $isEmployeeWrite = str_starts_with($route->uri(), 'employees') && array_diff($route->methods(), ['GET', 'HEAD']) !== [];
        if (! $needsMissing && ! $isEmployeeWrite) {
            continue;
        }
        $url = $this->base . preg_replace('/\{[^}]+\}/', '1', '/' . ltrim($route->uri(), '/'));
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $status = ($this->as)($mgr)->call($method, $url)->getStatusCode();
            $checked++;
            if ($status !== 403) {
                $leaks[] = "$method {$route->uri()} => $status";
            }
        }
    }

    expect($checked)->toBeGreaterThan(60)->and($leaks)->toBe([]);
});

test('fixture-backed: a manager cannot change or delete real records and nothing is altered', function () {
    $mgr = $this->reg['tenant']->run(function () {
        $u = new User(['name' => 'm', 'email' => 'mgr-fix@tenant-test.example', 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole(TenantRoleCatalog::MANAGER);

        return $u;
    });
    $emp = app(CreateEmployeeAction::class)->execute(data: ['first_name' => 'Fix', 'last_name' => 'Ture', 'employment_status' => 'active', 'hire_date' => '2026-01-05'], tenantId: $this->tid, actorId: null);
    $req = LeaveRequest::create(['tenant_id' => $this->tid, 'employee_id' => $emp->id, 'leave_type_id' => $this->annual->id, 'start_date' => '2026-12-06', 'end_date' => '2026-12-06', 'days' => 1, 'status' => 'pending']);
    $job = \App\Modules\Recruitment\Models\JobOpening::create(['tenant_id' => $this->tid, 'title' => 'Fixture job', 'employment_type' => 'full_time', 'positions' => 1, 'status' => 'open']);
    $cand = \App\Modules\Recruitment\Models\Candidate::create(['tenant_id' => $this->tid, 'job_opening_id' => $job->id, 'name' => 'Cand', 'stage' => 'applied']);
    $cycle = \App\Modules\Performance\Models\ReviewCycle::create(['tenant_id' => $this->tid, 'name' => 'Cycle', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'draft']);
    \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
    $run = PayrollRun::create(['tenant_id' => $this->tid, 'year' => 2026, 'month' => 11, 'rule_set_id' => 0, 'status' => 'draft']);
    \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

    $b = $this->base;
    $attempts = [
        ['DELETE', "/employees/{$emp->id}"],
        ['PUT', "/employees/{$emp->id}", ['first_name' => 'Hacked', 'last_name' => 'X', 'employment_status' => 'active']],
        ['POST', "/employees/{$emp->id}/identity-records", ['identity_type' => 'national_id', 'identity_number' => '12345678901234']],
        ['PUT', "/leave/types/{$this->annual->id}", ['name' => 'Hacked', 'annual_days' => 99]],
        ['DELETE', "/leave/types/{$this->annual->id}"],
        ['PUT', "/leave/requests/{$req->id}", ['leave_type_id' => $this->annual->id, 'start_date' => '2026-12-07', 'end_date' => '2026-12-07']],
        ['POST', "/leave/requests/{$req->id}/cancel"],
        ['DELETE', "/recruitment/jobs/{$job->id}"],
        ['DELETE', "/recruitment/candidates/{$cand->id}"],
        ['POST', "/recruitment/candidates/{$cand->id}/hire"],
        ['DELETE', "/performance/cycles/{$cycle->id}"],
        ['POST', "/payroll/runs/{$run->id}/approve"],
        ['DELETE', "/payroll/runs/{$run->id}"],
        ['PUT', '/settings', ['display_name' => 'Hacked']],
        ['PUT', "/team/{$this->reg['user']->id}/role", ['role' => 'Employee']],
        ['PUT', "/team/{$this->reg['user']->id}/active", ['active' => false]],
        ['PUT', "/team/{$this->reg['user']->id}/reset-access", ['password' => 'BrandNewStart123!']],
    ];
    foreach ($attempts as $a) {
        [$method, $path] = $a;
        $body = $a[2] ?? [];
        $res = ($this->as)($mgr)->call($method, $b . $path, $body);
        expect($res->getStatusCode())->toBe(403, "$method $path");
    }

    expect(Employee::find($emp->id)->first_name)->toBe('Fix')
        ->and(LeaveType::find($this->annual->id)->name)->toBe('Annual')
        ->and($req->fresh()->status)->toBe('pending')->and((string) $req->fresh()->start_date->toDateString())->toBe('2026-12-06')
        ->and(\App\Modules\Recruitment\Models\JobOpening::find($job->id))->not->toBeNull()
        ->and(\App\Modules\Recruitment\Models\Candidate::find($cand->id)->employee_id)->toBeNull()
        ->and(\App\Modules\Performance\Models\ReviewCycle::find($cycle->id))->not->toBeNull()
        ->and($run->fresh()->status)->toBe('draft')
        ->and($this->reg['user']->fresh()->disabled_at)->toBeNull();
});

test('fixture-backed: another company owner cannot touch this company records by real id', function () {
    $emp = app(CreateEmployeeAction::class)->execute(data: ['first_name' => 'Tenant', 'last_name' => 'A', 'employment_status' => 'active', 'hire_date' => '2026-01-05'], tenantId: $this->tid, actorId: null);
    $req = LeaveRequest::create(['tenant_id' => $this->tid, 'employee_id' => $emp->id, 'leave_type_id' => $this->annual->id, 'start_date' => '2026-12-06', 'end_date' => '2026-12-06', 'days' => 1, 'status' => 'pending']);
    $job = \App\Modules\Recruitment\Models\JobOpening::create(['tenant_id' => $this->tid, 'title' => 'A job', 'employment_type' => 'full_time', 'positions' => 1, 'status' => 'open']);
    $cand = \App\Modules\Recruitment\Models\Candidate::create(['tenant_id' => $this->tid, 'job_opening_id' => $job->id, 'name' => 'Cand', 'stage' => 'applied']);
    $cycle = \App\Modules\Performance\Models\ReviewCycle::create(['tenant_id' => $this->tid, 'name' => 'Cycle', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'draft']);
    $review = \App\Modules\Performance\Models\PerformanceReview::create(['tenant_id' => $this->tid, 'review_cycle_id' => $cycle->id, 'employee_id' => $emp->id, 'status' => 'draft']);
    \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
    $run = PayrollRun::create(['tenant_id' => $this->tid, 'year' => 2026, 'month' => 11, 'rule_set_id' => 0, 'status' => 'draft']);
    \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
    $slip = Payslip::create(['tenant_id' => $this->tid, 'payroll_run_id' => $run->id, 'employee_id' => $emp->id, 'gross' => 1000, 'net_pay' => 900]);

    $other = app(RegisterCompanyAction::class)->execute(companyName: 'Intruder Co', adminName: 'Intruder', email: 'intruder@tenant-test.example', password: 'StrongPassword123!');
    $ob = 'http://' . $other['domain'];
    $as = fn () => $this->actingAs($other['user'], 'tenant')->withSession(['tenant_session_version' => 1]);

    $attempts = [
        ['GET', "/employees/{$emp->id}"], ['PUT', "/employees/{$emp->id}", ['first_name' => 'X', 'middle_name' => 'M', 'last_name' => 'Y', 'employment_status' => 'draft']], ['DELETE', "/employees/{$emp->id}"],
        ['PUT', "/leave/types/{$this->annual->id}", ['name' => 'X', 'annual_days' => 1]], ['DELETE', "/leave/types/{$this->annual->id}"],
        ['POST', "/leave/requests/{$req->id}/review", ['decision' => 'approve']], ['POST', "/leave/requests/{$req->id}/cancel"],
        ['PUT', "/leave/requests/{$req->id}", ['leave_type_id' => $this->annual->id, 'start_date' => '2026-12-07', 'end_date' => '2026-12-07']],
        ['PUT', "/recruitment/jobs/{$job->id}", ['title' => 'X', 'employment_type' => 'full_time', 'positions' => 1, 'status' => 'open']], ['DELETE', "/recruitment/jobs/{$job->id}"],
        ['POST', "/recruitment/candidates/{$cand->id}/move", ['stage' => 'offer']], ['POST', "/recruitment/candidates/{$cand->id}/hire"], ['DELETE', "/recruitment/candidates/{$cand->id}"],
        ['PUT', "/performance/cycles/{$cycle->id}", ['name' => 'X', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']], ['DELETE', "/performance/cycles/{$cycle->id}"],
        ['POST', "/performance/reviews/{$review->id}/finalize"], ['DELETE', "/performance/reviews/{$review->id}"],
        ['GET', "/payroll/runs/{$run->id}"], ['GET', "/payroll/runs/{$run->id}/export"], ['POST', "/payroll/runs/{$run->id}/approve"], ['DELETE', "/payroll/runs/{$run->id}"], ['POST', "/payroll/runs/{$run->id}/recalculate"],
        ['POST', "/payroll/employees/{$emp->id}/compensation", ['basic_salary' => 1, 'effective_from' => '2026-01-01']],
    ];
    foreach ($attempts as $a) {
        $res = $as()->call($a[0], $ob . $a[1], $a[2] ?? []);
        expect($res->getStatusCode())->toBeIn([403, 404], "{$a[0]} {$a[1]} => {$res->getStatusCode()}");
    }

    expect(Employee::find($emp->id)->first_name)->toBe('Tenant')
        ->and(LeaveType::find($this->annual->id))->not->toBeNull()
        ->and($req->fresh()->status)->toBe('pending')
        ->and(\App\Modules\Recruitment\Models\Candidate::find($cand->id)->employee_id)->toBeNull()
        ->and(\App\Modules\Performance\Models\PerformanceReview::find($review->id)->status)->toBe('draft')
        ->and($run->fresh()->status)->toBe('draft')
        ->and(Payslip::find($slip->id))->not->toBeNull();
});

test('mobile token stops working when the account is deactivated or unlinked', function () {
    [$u, $emp] = ($this->account)('mob@tenant-test.example', 'Mob');
    $plain = $u->createToken('phone', ['attendance:mobile'])->plainTextToken;
    $api = 'http://' . $this->reg['domain'] . '/api/v1/attendance/status';

    $this->withToken($plain)->getJson($api)->assertOk();

    ($this->as)($this->reg['user'])->put("{$this->base}/team/{$u->id}/active", ['active' => false])->assertSessionHasNoErrors();
    $this->app['auth']->forgetGuards();
    $this->withToken($plain)->getJson($api)->assertStatus(401);

    // reactivate, new token, then unlink the employee: old token dies and a fresh one cannot reach the data
    ($this->as)($this->reg['user'])->put("{$this->base}/team/{$u->id}/active", ['active' => true]);
    $plain2 = $u->fresh()->createToken('phone', ['attendance:mobile'])->plainTextToken;
    $this->app['auth']->forgetGuards();
    ($this->as)($this->reg['user'])->put("{$this->base}/employees/{$emp->id}", ['first_name' => 'Mob', 'middle_name' => '', 'last_name' => 'Self', 'employment_status' => 'active', 'hire_date' => '2026-01-05', 'user_id' => ''])->assertSessionHasNoErrors();
    $this->app['auth']->forgetGuards();
    $this->withToken($plain2)->getJson($api)->assertStatus(401);
});
