<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Attendance\Models\AttendanceCorrection;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSession;
use App\Modules\Attendance\Models\OvertimeRequest;
use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function reviewCompany(string $name, string $email): array
{
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);
    $reg = app(RegisterCompanyAction::class)->execute(companyName: $name, adminName: $name . ' Admin', email: $email, password: 'StrongPassword123!');
    $tid = (string) $reg['tenant']->id;
    $emp = app(CreateEmployeeAction::class)->execute(
        data: ['first_name' => 'Worker', 'last_name' => $name, 'employment_status' => 'active'], tenantId: $tid, actorId: null,
    );
    $record = AttendanceRecord::create(['tenant_id' => $tid, 'employee_id' => $emp->id, 'attendance_date' => '2026-11-20']);
    $session = AttendanceSession::create([
        'tenant_id' => $tid, 'attendance_record_id' => $record->id, 'employee_id' => $emp->id, 'session_number' => 1,
        'check_in_at' => Carbon::parse('2026-11-20 09:30:00'), 'check_out_at' => Carbon::parse('2026-11-20 17:00:00'),
        'break_minutes' => 30, 'worked_minutes' => 420, 'status' => 'completed',
    ]);
    $requester = new User(['name' => 'Requester', 'email' => 'req@' . $email, 'password' => 'StrongPassword123!']);
    $requester->forceFill(['tenant_id' => $tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();

    return $reg + ['tid' => $tid, 'emp' => $emp, 'record' => $record, 'session' => $session, 'requester' => $requester, 'base' => 'http://' . $reg['domain']];
}

function asUser(User $u)
{
    return test()->actingAs($u, 'tenant')->withSession(['tenant_session_version' => 1]);
}

function userWithRole(array $c, string $role, string $email): User
{
    return $c['tenant']->run(function () use ($c, $role, $email) {
        $u = new User(['name' => $email, 'email' => $email, 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $c['tid'], 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole($role);

        return $u;
    });
}

beforeEach(fn () => $this->withoutVite());

test('approving a correction rewrites the session times and the daily summary', function () {
    $c = reviewCompany('Review Co', 'owner@review.test');
    $corr = AttendanceCorrection::create([
        'tenant_id' => $c['tid'], 'attendance_session_id' => $c['session']->id, 'employee_id' => $c['emp']->id,
        'requested_by_user_id' => $c['requester']->id, 'requested_check_in' => Carbon::parse('2026-11-20 09:00:00'),
        'reason' => 'Forgot to check in', 'status' => 'pending',
    ]);

    asUser($c['user'])->post("{$c['base']}/attendance/requests/correction/{$corr->id}/review", ['decision' => 'approve', 'note' => 'ok'])
        ->assertSessionHasNoErrors();

    $s = $c['session']->fresh();
    expect($s->check_in_at->format('H:i'))->toBe('09:00')->and($s->worked_minutes)->toBe(450)
        ->and($c['record']->fresh()->total_worked_minutes)->toBe(450)
        ->and($corr->fresh()->status)->toBe('approved')->and($corr->fresh()->reviewed_by_user_id)->toBe($c['user']->id);

    asUser($c['user'])->post("{$c['base']}/attendance/requests/correction/{$corr->id}/review", ['decision' => 'reject'])
        ->assertSessionHasErrors('status');
});

test('a correction that would move the day or end before it starts is refused and changes nothing', function () {
    $c = reviewCompany('Bad Corr Co', 'owner@badcorr.test');
    $mk = fn (array $times) => AttendanceCorrection::create([
        'tenant_id' => $c['tid'], 'attendance_session_id' => $c['session']->id, 'employee_id' => $c['emp']->id,
        'requested_by_user_id' => $c['requester']->id, 'reason' => 'x', 'status' => 'pending',
    ] + $times);

    $nextDay = $mk(['requested_check_in' => Carbon::parse('2026-11-21 09:00:00')]);
    $backwards = $mk(['requested_check_out' => Carbon::parse('2026-11-20 08:00:00')]);

    foreach ([$nextDay, $backwards] as $corr) {
        asUser($c['user'])->post("{$c['base']}/attendance/requests/correction/{$corr->id}/review", ['decision' => 'approve'])->assertSessionHasErrors('status');
        expect($corr->fresh()->status)->toBe('pending');
    }
    expect($c['session']->fresh()->worked_minutes)->toBe(420);
});

test('approved overtime is added once to the session and the day, rejected overtime changes nothing', function () {
    $c = reviewCompany('Ot Co', 'owner@ot.test');
    $mk = fn (int $m) => OvertimeRequest::create(['tenant_id' => $c['tid'], 'attendance_session_id' => $c['session']->id, 'employee_id' => $c['emp']->id, 'requested_minutes' => $m, 'status' => 'pending']);
    $a = $mk(60);
    $b = $mk(30);

    asUser($c['user'])->post("{$c['base']}/attendance/requests/overtime/{$a->id}/review", ['decision' => 'approve'])->assertSessionHasNoErrors();
    asUser($c['user'])->post("{$c['base']}/attendance/requests/overtime/{$a->id}/review", ['decision' => 'approve'])->assertSessionHasErrors('status');
    asUser($c['user'])->post("{$c['base']}/attendance/requests/overtime/{$b->id}/review", ['decision' => 'reject'])->assertSessionHasNoErrors();

    expect($c['session']->fresh()->overtime_minutes)->toBe(60)->and($c['record']->fresh()->overtime_minutes)->toBe(60);
});

test('only attendance managers can review, and another company cannot touch these requests', function () {
    $c = reviewCompany('Gate Co', 'owner@gate.test');
    $other = reviewCompany('Other Gate Co', 'owner@othergate.test');

    $corr = AttendanceCorrection::create([
        'tenant_id' => $c['tid'], 'attendance_session_id' => $c['session']->id, 'employee_id' => $c['emp']->id,
        'requested_by_user_id' => $c['requester']->id, 'requested_check_in' => Carbon::parse('2026-11-20 09:00:00'), 'reason' => 'x', 'status' => 'pending',
    ]);
    $ot = OvertimeRequest::create(['tenant_id' => $c['tid'], 'attendance_session_id' => $c['session']->id, 'employee_id' => $c['emp']->id, 'requested_minutes' => 20, 'status' => 'pending']);

    foreach ([TenantRoleCatalog::MANAGER => 'mgr@gate.test', TenantRoleCatalog::EMPLOYEE => 'emp@gate.test'] as $role => $email) {
        $u = userWithRole($c, $role, $email);
        asUser($u)->get("{$c['base']}/attendance/requests")->assertForbidden();
        asUser($u)->post("{$c['base']}/attendance/requests/correction/{$corr->id}/review", ['decision' => 'approve'])->assertForbidden();
        asUser($u)->post("{$c['base']}/attendance/requests/overtime/{$ot->id}/review", ['decision' => 'approve'])->assertForbidden();
    }

    // The other company's owner, on their own domain and on ours, cannot act on these ids.
    asUser($other['user'])->post("{$other['base']}/attendance/requests/correction/{$corr->id}/review", ['decision' => 'approve'])->assertNotFound();
    asUser($other['user'])->post("{$other['base']}/attendance/requests/overtime/{$ot->id}/review", ['decision' => 'approve'])->assertNotFound();
    asUser($other['user'])->post("{$c['base']}/attendance/requests/overtime/{$ot->id}/review", ['decision' => 'approve'])->assertStatus(403);

    expect($corr->fresh()->status)->toBe('pending')->and($ot->fresh()->status)->toBe('pending');

    asUser($c['user'])->get("{$c['base']}/attendance/requests")->assertOk()
        ->assertInertia(fn ($p) => $p->has('corrections', 1)->has('overtime', 1));
    asUser($other['user'])->get("{$other['base']}/attendance/requests")->assertOk()
        ->assertInertia(fn ($p) => $p->has('corrections', 0)->has('overtime', 0));
});
