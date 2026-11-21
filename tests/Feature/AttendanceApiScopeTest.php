<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Attendance\Models\AttendanceBreak;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSession;
use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
        'tenancy.central_domains' => ['humexa.test'],
    ]);
});

function scopeCompany(string $name, string $email): array
{
    $registration = app(RegisterCompanyAction::class)->execute(
        companyName: $name,
        adminName: $name . ' Admin',
        email: $email,
        password: 'StrongPassword123!',
    );

    $makeEmployee = function (User $user, string $first) use ($registration) {
        return app(CreateEmployeeAction::class)->execute(
            data: ['user_id' => $user->id, 'first_name' => $first, 'last_name' => 'Worker', 'employment_status' => 'active'],
            tenantId: (string) $registration['tenant']->id,
            actorId: null,
        );
    };

    $second = new User(['name' => 'Second Worker', 'email' => 'second@' . $email, 'password' => 'StrongPassword123!']);
    $second->forceFill(['tenant_id' => $registration['tenant']->id, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();

    $a = $makeEmployee($registration['user'], 'Alpha');
    $b = $makeEmployee($second, 'Bravo');

    return $registration + ['userA' => $registration['user'], 'userB' => $second, 'empA' => $a, 'empB' => $b];
}

function openSessionFor(array $company, $employee): AttendanceSession
{
    $record = AttendanceRecord::create([
        'tenant_id' => $company['tenant']->id,
        'employee_id' => $employee->id,
        'attendance_date' => now()->toDateString(),
    ]);

    return AttendanceSession::create([
        'tenant_id' => $company['tenant']->id,
        'attendance_record_id' => $record->id,
        'employee_id' => $employee->id,
        'session_number' => 1,
        'check_in_at' => now()->subHour(),
        'check_in_source' => 'mobile',
        'status' => 'open',
    ]);
}

test('an employee cannot use a colleague attendance session in the same company', function () {
    $company = scopeCompany('Scope Alpha', 'alpha@scope.test');
    $sessionB = $company['tenant']->run(fn () => openSessionFor($company, $company['empB']));

    $token = $company['userA']->createToken('A', ['attendance:mobile'])->plainTextToken;
    $base = 'http://' . $company['domain'] . '/api/v1/attendance';

    foreach ([
        ['/breaks/start', ['attendance_session_id' => $sessionB->id]],
        ['/breaks/end', ['attendance_session_id' => $sessionB->id]],
        ['/corrections', ['attendance_session_id' => $sessionB->id, 'reason' => 'x']],
        ['/overtime-requests', ['attendance_session_id' => $sessionB->id, 'requested_minutes' => 30]],
    ] as [$path, $payload]) {
        Auth::forgetGuards();
        $this->withToken($token)->postJson($base . $path, $payload)->assertNotFound();
    }

    expect(AttendanceBreak::query()->count())->toBe(0);
});

test('correction and overtime lists only show the caller own requests', function () {
    $company = scopeCompany('Scope Beta', 'beta@scope.test');
    $sessionB = $company['tenant']->run(fn () => openSessionFor($company, $company['empB']));
    $sessionB->forceFill(['check_out_at' => now()])->save();

    $tokenB = $company['userB']->createToken('B', ['attendance:mobile'])->plainTextToken;
    $tokenA = $company['userA']->createToken('A', ['attendance:mobile'])->plainTextToken;
    $base = 'http://' . $company['domain'] . '/api/v1/attendance';

    $this->withToken($tokenB)->postJson($base . '/corrections', ['attendance_session_id' => $sessionB->id, 'reason' => 'forgot'])->assertCreated();
    Auth::forgetGuards();
    $this->withToken($tokenB)->postJson($base . '/overtime-requests', ['attendance_session_id' => $sessionB->id, 'requested_minutes' => 20])->assertCreated();

    Auth::forgetGuards();
    $this->withToken($tokenA)->getJson($base . '/corrections')->assertOk()->assertJsonCount(0, 'data.data');
    Auth::forgetGuards();
    $this->withToken($tokenA)->getJson($base . '/overtime-requests')->assertOk()->assertJsonCount(0, 'data.data');
    Auth::forgetGuards();
    $this->withToken($tokenB)->getJson($base . '/corrections')->assertOk()->assertJsonCount(1, 'data.data');
});

test('a token from another company is rejected on every attendance endpoint', function () {
    $company = scopeCompany('Scope Gamma', 'gamma@scope.test');
    $other = scopeCompany('Scope Delta', 'delta@scope.test');

    $token = $other['userA']->createToken('Other', ['attendance:mobile'])->plainTextToken;
    $base = 'http://' . $company['domain'] . '/api/v1/attendance';

    foreach (['/policy', '/status', '/history', '/corrections', '/overtime-requests'] as $path) {
        Auth::forgetGuards();
        $this->withToken($token)->getJson($base . $path)->assertForbidden();
    }

    foreach (['/check-in', '/check-out', '/breaks/start', '/breaks/end', '/corrections', '/overtime-requests'] as $path) {
        Auth::forgetGuards();
        $this->withToken($token)->postJson($base . $path, [])->assertForbidden();
    }
});
