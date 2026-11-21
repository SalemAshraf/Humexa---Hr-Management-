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
