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


test('shifts are optional, explicit, and only affect late minutes of the first check-in', function () {
    [$u, $emp] = ($this->account)('ws@tenant-test.example', 'Shifty');
    $owner = $this->reg['user'];
    \App\Modules\Settings\Models\CompanySetting::query()->create(['tenant_id' => $this->tid, 'timezone' => 'Africa/Cairo']);
    $lateness = app(\App\Modules\Attendance\Services\ShiftLateness::class);
    $at = fn (string $local) => \Illuminate\Support\Carbon::parse($local, 'Africa/Cairo')->utc();

    // No shift: always 0 and nothing assigned by creating a shift.
    expect($lateness->minutesLate($emp->fresh(), $at('2026-11-10 11:00')))->toBe(0);
    ($this->as)($u)->post("{$this->base}/attendance/shifts", ['name' => 'X', 'start_time' => '09:00', 'end_time' => '17:00'])->assertForbidden();
    ($this->as)($owner)->post("{$this->base}/attendance/shifts", ['name' => 'Day', 'start_time' => '09:00', 'end_time' => '17:00', 'grace_minutes' => 10])->assertSessionHasNoErrors();
    ($this->as)($owner)->post("{$this->base}/attendance/shifts", ['name' => 'Day', 'start_time' => '09:00', 'end_time' => '17:00'])->assertSessionHasErrors('name');
    ($this->as)($owner)->post("{$this->base}/attendance/shifts", ['name' => 'Same', 'start_time' => '09:00', 'end_time' => '09:00'])->assertSessionHasErrors('end_time');
    $day = \App\Modules\Attendance\Models\AttendanceShift::where('tenant_id', $this->tid)->where('name', 'Day')->firstOrFail();
    expect($emp->fresh()->shift_id)->toBeNull();

    ($this->as)($owner)->put("{$this->base}/attendance/shifts/assign", ['employee_id' => $emp->id, 'shift_id' => $day->id])->assertSessionHasNoErrors();
    $emp = $emp->fresh();
    expect($lateness->minutesLate($emp, $at('2026-11-10 08:30')))->toBe(0)
        ->and($lateness->minutesLate($emp, $at('2026-11-10 09:10')))->toBe(0)
        ->and($lateness->minutesLate($emp, $at('2026-11-10 09:25')))->toBe(15);

    // Overnight 22:00 to 06:00 with company grace 5 (policy), check-in 01:00 is measured from 22:00 the evening before.
    AttendancePolicy::where('tenant_id', $this->tid)->update(['late_grace_minutes' => 5]);
    ($this->as)($owner)->post("{$this->base}/attendance/shifts", ['name' => 'Night', 'start_time' => '22:00', 'end_time' => '06:00'])->assertSessionHasNoErrors();
    $night = \App\Modules\Attendance\Models\AttendanceShift::where('tenant_id', $this->tid)->where('name', 'Night')->firstOrFail();
    ($this->as)($owner)->put("{$this->base}/attendance/shifts/assign", ['employee_id' => $emp->id, 'shift_id' => $night->id]);
    $emp = $emp->fresh();
    expect($lateness->minutesLate($emp, $at('2026-11-11 01:00')))->toBe(175)
        ->and($lateness->minutesLate($emp, $at('2026-11-10 21:30')))->toBe(0)
        ->and($lateness->minutesLate($emp, $at('2026-11-10 22:20')))->toBe(15);

    // Real check-in stores it, only on the first session of the day.
    ($this->as)($owner)->put("{$this->base}/attendance/shifts/assign", ['employee_id' => $emp->id, 'shift_id' => $day->id]);
    \Illuminate\Support\Carbon::setTestNow($at('2026-11-10 09:40'));
    ($this->as)($u)->post("{$this->base}/me/attendance/check-in")->assertSessionHasNoErrors();
    \Illuminate\Support\Carbon::setTestNow($at('2026-11-10 12:00'));
    ($this->as)($u)->post("{$this->base}/me/attendance/check-out")->assertSessionHasNoErrors();
    ($this->as)($u)->post("{$this->base}/me/attendance/check-in")->assertSessionHasNoErrors();
    \Illuminate\Support\Carbon::setTestNow();
    $sessions = \App\Modules\Attendance\Models\AttendanceSession::where('employee_id', $emp->id)->orderBy('session_number')->pluck('late_minutes')->all();
    expect($sessions)->toBe([30, 0]);

    // Deleting a shift in use is refused; an unused one can go.
    ($this->as)($owner)->delete("{$this->base}/attendance/shifts/{$day->id}")->assertSessionHasErrors('shift');
    ($this->as)($owner)->delete("{$this->base}/attendance/shifts/{$night->id}")->assertSessionHasNoErrors();
    ($this->as)($u)->get("{$this->base}/attendance/shifts")->assertForbidden();
    ($this->as)($owner)->get("{$this->base}/attendance/shifts")->assertOk();
});


test('shift can be edited, and lateness is measured in company wall-clock time across the Cairo DST change', function () {
    [$u, $emp] = ($this->account)('wd@tenant-test.example', 'Dst');
    $owner = $this->reg['user'];
    \App\Modules\Settings\Models\CompanySetting::query()->create(['tenant_id' => $this->tid, 'timezone' => 'Africa/Cairo']);
    ($this->as)($owner)->post("{$this->base}/attendance/shifts", ['name' => 'Office', 'start_time' => '09:00', 'end_time' => '17:00', 'grace_minutes' => 0]);
    $shift = \App\Modules\Attendance\Models\AttendanceShift::where('tenant_id', $this->tid)->firstOrFail();
    ($this->as)($owner)->put("{$this->base}/attendance/shifts/assign", ['employee_id' => $emp->id, 'shift_id' => $shift->id]);
    $lateness = app(\App\Modules\Attendance\Services\ShiftLateness::class);
    $at = fn (string $local) => \Illuminate\Support\Carbon::parse($local, 'Africa/Cairo')->utc();

    // Egypt moves clocks forward at midnight on the last Friday of April (2026-04-24). 09:30 local is still 30 late.
    foreach (['2026-04-23 09:30', '2026-04-24 09:30', '2026-04-25 09:30', '2026-10-30 09:30', '2026-10-31 09:30'] as $when) {
        expect($lateness->minutesLate($emp->fresh(), $at($when)))->toBe(30);
    }

    ($this->as)($owner)->put("{$this->base}/attendance/shifts/{$shift->id}", ['name' => 'Office', 'start_time' => '08:00', 'end_time' => '16:00', 'grace_minutes' => 15])->assertSessionHasNoErrors();
    expect($shift->fresh()->start_time)->toStartWith('08:00')->and($shift->fresh()->grace_minutes)->toBe(15)
        ->and($lateness->minutesLate($emp->fresh(), $at('2026-04-24 09:30')))->toBe(75);
    ($this->as)($owner)->put("{$this->base}/attendance/shifts/{$shift->id}", ['name' => 'Office', 'start_time' => '08:00', 'end_time' => '08:00'])->assertSessionHasErrors('end_time');
    ($this->as)($u)->put("{$this->base}/attendance/shifts/{$shift->id}", ['name' => 'Hack', 'start_time' => '00:00', 'end_time' => '01:00'])->assertForbidden();
});
