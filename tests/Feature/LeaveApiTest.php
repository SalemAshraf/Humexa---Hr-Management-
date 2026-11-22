<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
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

function leaveCompany(string $name, string $email): array
{
    $registration = app(RegisterCompanyAction::class)->execute(companyName: $name, adminName: $name . ' Admin', email: $email, password: 'StrongPassword123!');
    $tenantId = (string) $registration['tenant']->id;
    $mine = app(CreateEmployeeAction::class)->execute(data: ['user_id' => $registration['user']->id, 'first_name' => 'Alpha', 'last_name' => 'Worker', 'employment_status' => 'active'], tenantId: $tenantId, actorId: null);
    $other = new User(['name' => 'Second', 'email' => 'second@' . $email, 'password' => 'StrongPassword123!']);
    $other->forceFill(['tenant_id' => $registration['tenant']->id, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
    $theirs = app(CreateEmployeeAction::class)->execute(data: ['user_id' => $other->id, 'first_name' => 'Bravo', 'last_name' => 'Worker', 'employment_status' => 'active'], tenantId: $tenantId, actorId: null);

    return $registration + ['tenantId' => $tenantId, 'mine' => $mine, 'theirs' => $theirs, 'other' => $other];
}

test('an employee sees balances, requests leave through the shared rules and withdraws it', function () {
    $c = leaveCompany('Leave Api', 'owner@leaveapi.test');
    $type = $c['tenant']->run(fn () => LeaveType::query()->create(['tenant_id' => $c['tenantId'], 'name' => 'Annual', 'annual_days' => 21, 'is_active' => true]));
    $token = $c['user']->createToken('phone', ['attendance:mobile'])->plainTextToken;
    $base = 'http://' . $c['domain'] . '/api/v1/leave';

    $this->withToken($token)->getJson($base)->assertOk()->assertJsonPath('data.balances.0.leave_type_id', $type->id);

    $monday = now()->next('Monday')->toDateString();
    Auth::forgetGuards();
    $res = $this->withToken($token)->postJson($base, ['leave_type_id' => $type->id, 'start_date' => $monday, 'end_date' => $monday, 'reason' => 'Family'])->assertCreated();
    $id = $res->json('data.id');

    // Overlap rule from LeaveService applies to the API too.
    Auth::forgetGuards();
    $this->withToken($token)->postJson($base, ['leave_type_id' => $type->id, 'start_date' => $monday, 'end_date' => $monday])->assertStatus(422);

    Auth::forgetGuards();
    $this->withToken($token)->postJson($base . '/' . $id . '/cancel')->assertOk();
    Auth::forgetGuards();
    $this->withToken($token)->postJson($base . '/' . $id . '/cancel')->assertStatus(422);
    tenancy()->end();
    expect(LeaveRequest::query()->withoutGlobalScopes()->find($id)->status)->toBe(LeaveRequest::CANCELLED);
});

test('an employee cannot withdraw a colleague leave request', function () {
    $c = leaveCompany('Leave Scope', 'owner@leavescope.test');
    $type = $c['tenant']->run(fn () => LeaveType::query()->create(['tenant_id' => $c['tenantId'], 'name' => 'Annual', 'annual_days' => 21, 'is_active' => true]));
    $tokenB = $c['other']->createToken('b', ['attendance:mobile'])->plainTextToken;
    $tokenA = $c['user']->createToken('a', ['attendance:mobile'])->plainTextToken;
    $base = 'http://' . $c['domain'] . '/api/v1/leave';
    $monday = now()->next('Monday')->toDateString();

    $id = $this->withToken($tokenB)->postJson($base, ['leave_type_id' => $type->id, 'start_date' => $monday, 'end_date' => $monday])->assertCreated()->json('data.id');
    Auth::forgetGuards();
    $this->withToken($tokenA)->postJson($base . '/' . $id . '/cancel')->assertNotFound();
    Auth::forgetGuards();
    $this->withToken($tokenA)->getJson($base)->assertOk()->assertJsonCount(0, 'data.requests');
});
