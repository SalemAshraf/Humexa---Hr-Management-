<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Employees\Models\Employee;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceRecord;
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


test('an employee can check in and out from the web and it is recorded as web', function () {
    [$u, $emp] = ($this->account)('wa@tenant-test.example', 'Wanda');

    ($this->as)($u)->post("{$this->base}/me/attendance/check-in")->assertSessionHasNoErrors();
    ($this->as)($u)->post("{$this->base}/me/attendance/check-in")->assertSessionHasErrors('attendance');
    ($this->as)($u)->get("{$this->base}/me")->assertInertia(fn ($p) => $p->where('clock.state', 'checked_in'));
    ($this->as)($u)->post("{$this->base}/me/attendance/check-out")->assertSessionHasNoErrors();

    $session = \App\Modules\Attendance\Models\AttendanceSession::where('employee_id', $emp->id)->firstOrFail();
    expect($session->check_in_source)->toBe('web')->and($session->status)->toBe('completed');
    ($this->as)($u)->get("{$this->base}/me")->assertInertia(fn ($p) => $p->where('clock.state', 'not_checked_in'));
});

test('web check-in respects the company policy and the location rule', function () {
    [$u] = ($this->account)('wb@tenant-test.example', 'Wally');

    AttendancePolicy::where('tenant_id', $this->tid)->update(['mobile_checkin_enabled' => false]);
    ($this->as)($u)->post("{$this->base}/me/attendance/check-in")->assertSessionHasErrors('attendance');

    AttendancePolicy::where('tenant_id', $this->tid)->update(['mobile_checkin_enabled' => true, 'location_verification_enabled' => true]);
    ($this->as)($u)->post("{$this->base}/me/attendance/check-in")->assertSessionHasErrors('location');
});

test('HR can add a manual entry only when the policy allows it, without overlaps, and employees cannot', function () {
    [$u, $emp] = ($this->account)('wc@tenant-test.example', 'Walt');
    $owner = $this->reg['user'];
    $entry = ['employee_id' => $emp->id, 'date' => '2026-11-10', 'check_in' => '09:00', 'check_out' => '17:30'];

    ($this->as)($owner)->post("{$this->base}/attendance/manual", $entry)->assertSessionHasErrors('manual');

    ($this->as)($owner)->put("{$this->base}/attendance/policy", ['mobile_checkin_enabled' => true, 'manual_attendance_enabled' => true])->assertSessionHasNoErrors();
    ($this->as)($owner)->post("{$this->base}/attendance/manual", $entry)->assertSessionHasNoErrors();

    $record = AttendanceRecord::where('employee_id', $emp->id)->firstOrFail();
    expect($record->total_worked_minutes)->toBe(510);

    ($this->as)($owner)->post("{$this->base}/attendance/manual", [...$entry, 'check_in' => '10:00', 'check_out' => '11:00'])->assertSessionHasErrors('manual');
    ($this->as)($owner)->post("{$this->base}/attendance/manual", [...$entry, 'check_in' => '18:00', 'check_out' => '17:00'])->assertSessionHasErrors('check_out');
    ($this->as)($owner)->post("{$this->base}/attendance/manual", [...$entry, 'date' => '2999-01-01'])->assertSessionHasErrors('date');

    ($this->as)($u)->post("{$this->base}/attendance/manual", $entry)->assertForbidden();
    ($this->as)($u)->put("{$this->base}/attendance/policy", ['mobile_checkin_enabled' => false, 'manual_attendance_enabled' => false])->assertForbidden();
});
