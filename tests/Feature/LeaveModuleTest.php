<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Employees\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
        'tenancy.central_domains' => ['humexa.test'],
    ]);

    $this->reg = app(RegisterCompanyAction::class)->execute(
        companyName: 'Leave Test Company', adminName: 'Leave Admin',
        email: 'leave-admin@tenant-test.example', password: 'StrongPassword123!',
    );
    $this->tid = (string) $this->reg['tenant']->id;
    $this->base = 'http://' . $this->reg['domain'];

    $this->mkEmployee = fn (string $tid, string $first, array $extra = []) => tap(
        app(CreateEmployeeAction::class)->execute(
            data: ['first_name' => $first, 'last_name' => 'Tester', 'employment_status' => 'active', 'hire_date' => '2026-01-05'],
            tenantId: $tid,
            actorId: null,
        ),
        fn ($e) => $extra ? Employee::query()->whereKey($e->id)->update($extra) : null,
    );

    $this->mkUser = function (string $role, string $email) {
        return $this->reg['tenant']->run(function () use ($role, $email) {
            $user = new User(['name' => $email, 'email' => $email, 'password' => 'StrongPassword123!']);
            $user->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
            $user->assignRole($role);

            return $user;
        });
    };

    $this->as = fn (User $u) => $this->actingAs($u, 'tenant')->withSession(['tenant_session_version' => 1]);

    $this->annual = LeaveType::create(['tenant_id' => $this->tid, 'name' => 'Annual', 'annual_days' => 5, 'is_paid' => true, 'is_active' => true]);
});

test('owner records leave, working days skip the configured weekend, and balance is enforced', function () {
    $emp = ($this->mkEmployee)($this->tid, 'Mona');

    // 2026-11-22 is a Sunday. Sun to Sat contains 5 working days with a Fri/Sat weekend.
    ($this->as)($this->reg['user'])->post("{$this->base}/leave/requests", [
        'employee_id' => $emp->id, 'leave_type_id' => $this->annual->id,
        'start_date' => '2026-11-22', 'end_date' => '2026-11-28',
    ])->assertSessionHasNoErrors();

    expect(LeaveRequest::first()->days)->toBe(5.0);

    ($this->as)($this->reg['user'])->post("{$this->base}/leave/requests", [
        'employee_id' => $emp->id, 'leave_type_id' => $this->annual->id,
        'start_date' => '2026-11-24', 'end_date' => '2026-11-24',
    ])->assertSessionHasErrors('start_date'); // overlap

    ($this->as)($this->reg['user'])->post("{$this->base}/leave/requests", [
        'employee_id' => $emp->id, 'leave_type_id' => $this->annual->id,
        'start_date' => '2026-12-06', 'end_date' => '2026-12-06',
    ])->assertSessionHasErrors('end_date'); // balance used up by pending request

    expect(LeaveRequest::count())->toBe(1);
});

test('a request cannot be reviewed twice and approval counts against the balance', function () {
    $emp = ($this->mkEmployee)($this->tid, 'Omar');
    $req = LeaveRequest::create([
        'tenant_id' => $this->tid, 'employee_id' => $emp->id, 'leave_type_id' => $this->annual->id,
        'start_date' => '2026-11-22', 'end_date' => '2026-11-23', 'days' => 2, 'status' => 'pending',
    ]);

    ($this->as)($this->reg['user'])->post("{$this->base}/leave/requests/{$req->id}/review", ['decision' => 'approve'])
        ->assertSessionHasNoErrors();
    expect($req->fresh()->status)->toBe('approved')->and($req->fresh()->reviewed_by_user_id)->not->toBeNull();

    ($this->as)($this->reg['user'])->post("{$this->base}/leave/requests/{$req->id}/review", ['decision' => 'reject'])
        ->assertSessionHasErrors('status');
    expect($req->fresh()->status)->toBe('approved');
});

test('another company cannot see or touch this companys leave data', function () {
    $other = app(RegisterCompanyAction::class)->execute(
        companyName: 'Other Leave Co', adminName: 'Other Admin',
        email: 'other-leave@tenant-test.example', password: 'StrongPassword123!',
    );
    $otherTid = (string) $other['tenant']->id;

    $mine = ($this->mkEmployee)($this->tid, 'Mine');
    $theirs = ($this->mkEmployee)($otherTid, 'Theirs');
    $theirType = LeaveType::create(['tenant_id' => $otherTid, 'name' => 'Other annual', 'annual_days' => 10, 'is_paid' => true, 'is_active' => true]);
    $theirReq = LeaveRequest::create([
        'tenant_id' => $otherTid, 'employee_id' => $theirs->id, 'leave_type_id' => $theirType->id,
        'start_date' => '2026-11-22', 'end_date' => '2026-11-22', 'days' => 1, 'status' => 'pending',
    ]);

    // Admin of company A using company B's ids on company A's domain.
    ($this->as)($this->reg['user'])->post("{$this->base}/leave/requests", [
        'employee_id' => $theirs->id, 'leave_type_id' => $this->annual->id,
        'start_date' => '2026-11-22', 'end_date' => '2026-11-22',
    ])->assertNotFound();
    ($this->as)($this->reg['user'])->post("{$this->base}/leave/requests", [
        'employee_id' => $mine->id, 'leave_type_id' => $theirType->id,
        'start_date' => '2026-11-22', 'end_date' => '2026-11-22',
    ])->assertNotFound();
    ($this->as)($this->reg['user'])->post("{$this->base}/leave/requests/{$theirReq->id}/review", ['decision' => 'approve'])->assertNotFound();
    ($this->as)($this->reg['user'])->post("{$this->base}/leave/requests/{$theirReq->id}/cancel")->assertNotFound();

    expect($theirReq->fresh()->status)->toBe('pending');

    ($this->as)($this->reg['user'])->get("{$this->base}/leave")->assertOk()
        ->assertInertia(fn ($p) => $p->where('requests.total', 0)->where('summary.pending', 0));

    // Admin of company B on B's domain does not see A's data either.
    LeaveRequest::create([
        'tenant_id' => $this->tid, 'employee_id' => $mine->id, 'leave_type_id' => $this->annual->id,
        'start_date' => '2026-11-22', 'end_date' => '2026-11-22', 'days' => 1, 'status' => 'pending',
    ]);
    $this->actingAs($other['user'], 'tenant')->withSession(['tenant_session_version' => 1])
        ->get('http://' . $other['domain'] . '/leave')->assertOk()
        ->assertInertia(fn ($p) => $p->where('requests.total', 1));
});

test('a manager only sees and reviews direct reports and never their own leave', function () {
    $manager = ($this->mkUser)(TenantRoleCatalog::MANAGER, 'mgr@tenant-test.example');
    $mgrEmp = ($this->mkEmployee)($this->tid, 'Boss', ['user_id' => $manager->id]);
    $report = ($this->mkEmployee)($this->tid, 'Report', ['manager_id' => $mgrEmp->id]);
    $stranger = ($this->mkEmployee)($this->tid, 'Stranger');

    $mk = fn ($e) => LeaveRequest::create([
        'tenant_id' => $this->tid, 'employee_id' => $e->id, 'leave_type_id' => $this->annual->id,
        'start_date' => '2026-11-22', 'end_date' => '2026-11-22', 'days' => 1, 'status' => 'pending',
    ]);
    $reportReq = $mk($report);
    $strangerReq = $mk($stranger);
    $ownReq = $mk($mgrEmp);

    ($this->as)($manager)->get("{$this->base}/leave")->assertOk()
        ->assertInertia(fn ($p) => $p->where('requests.total', 1)->where('summary.scope', 'direct_reports')->where('abilities.manage', false));

    ($this->as)($manager)->post("{$this->base}/leave/requests/{$strangerReq->id}/review", ['decision' => 'approve'])->assertForbidden();
    ($this->as)($manager)->post("{$this->base}/leave/requests/{$ownReq->id}/review", ['decision' => 'approve'])->assertForbidden();
    ($this->as)($manager)->post("{$this->base}/leave/requests/{$reportReq->id}/review", ['decision' => 'approve'])->assertSessionHasNoErrors();

    expect($reportReq->fresh()->status)->toBe('approved')
        ->and($strangerReq->fresh()->status)->toBe('pending')
        ->and($ownReq->fresh()->status)->toBe('pending');

    // A manager cannot create requests or leave types or cancel.
    ($this->as)($manager)->post("{$this->base}/leave/requests", [
        'employee_id' => $report->id, 'leave_type_id' => $this->annual->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-01',
    ])->assertForbidden();
    ($this->as)($manager)->post("{$this->base}/leave/types", ['name' => 'X', 'annual_days' => 1, 'is_paid' => true])->assertForbidden();
    ($this->as)($manager)->post("{$this->base}/leave/requests/{$reportReq->id}/cancel")->assertForbidden();
});

test('a plain employee account cannot open the leave module', function () {
    $staff = ($this->mkUser)(TenantRoleCatalog::EMPLOYEE, 'staff@tenant-test.example');

    ($this->as)($staff)->get("{$this->base}/leave")->assertForbidden();
    ($this->as)($staff)->post("{$this->base}/leave/requests", [])->assertForbidden();
});
