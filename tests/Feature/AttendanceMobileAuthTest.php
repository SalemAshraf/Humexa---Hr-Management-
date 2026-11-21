<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
        'tenancy.central_domains' => ['humexa.test'],
    ]);
});

function createAttendanceMobileAuthTenant(string $companyName, string $email): array
{
    return app(RegisterCompanyAction::class)->execute(
        companyName: $companyName,
        adminName: $companyName . ' Admin',
        email: $email,
        password: 'StrongPassword123!',
    );
}

function linkAttendanceMobileAuthEmployee(array $registration): void
{
    app(CreateEmployeeAction::class)->execute(
        data: [
            'user_id' => $registration['user']->id,
            'first_name' => 'Mobile',
            'last_name' => 'Employee',
            'employment_status' => 'active',
        ],
        tenantId: (string) $registration['tenant']->id,
        actorId: null,
    );
}

test('linked tenant employee can log in and receives an attendance-scoped Sanctum token', function () {
    $registration = createAttendanceMobileAuthTenant(
        'Mobile Auth Success',
        'mobile-auth-success@example.test',
    );

    linkAttendanceMobileAuthEmployee($registration);

    $response = $this->postJson(
        'http://' . $registration['domain'] . '/api/v1/auth/login',
        [
            'email' => 'mobile-auth-success@example.test',
            'password' => 'StrongPassword123!',
            'device_name' => 'Test Android Device',
        ],
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $registration['user']->id)
        ->assertJsonPath('data.user.email', 'mobile-auth-success@example.test')
        ->assertJsonStructure([
            'data' => [
                'token',
                'token_type',
                'user' => ['id', 'name', 'email'],
            ],
        ]);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();

    $token = PersonalAccessToken::query()
        ->where('tokenable_type', User::class)
        ->where('tokenable_id', $registration['user']->id)
        ->sole();

    expect($token->name)->toBe('Test Android Device')
        ->and($token->abilities)->toBe(['attendance:mobile']);

    $this->assertDatabaseCount('personal_access_tokens', 1);
});

test('mobile login rejects an incorrect password without creating a token', function () {
    $registration = createAttendanceMobileAuthTenant(
        'Mobile Auth Invalid Password',
        'mobile-auth-invalid@example.test',
    );

    linkAttendanceMobileAuthEmployee($registration);

    $this->postJson(
        'http://' . $registration['domain'] . '/api/v1/auth/login',
        [
            'email' => 'mobile-auth-invalid@example.test',
            'password' => 'WrongPassword123!',
            'device_name' => 'Test Device',
        ],
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('credentials');

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('mobile login rejects a tenant user without a linked employee profile', function () {
    $registration = createAttendanceMobileAuthTenant(
        'Mobile Auth No Employee',
        'mobile-auth-no-employee@example.test',
    );

    $this->postJson(
        'http://' . $registration['domain'] . '/api/v1/auth/login',
        [
            'email' => 'mobile-auth-no-employee@example.test',
            'password' => 'StrongPassword123!',
            'device_name' => 'Test Device',
        ],
    )
        ->assertForbidden()
        ->assertJsonPath('message', 'No employee profile is linked to this account.');

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('mobile login cannot authenticate a user from another tenant', function () {
    $first = createAttendanceMobileAuthTenant(
        'Mobile Auth Tenant Alpha',
        'mobile-auth-alpha@example.test',
    );

    $second = createAttendanceMobileAuthTenant(
        'Mobile Auth Tenant Beta',
        'mobile-auth-beta@example.test',
    );

    linkAttendanceMobileAuthEmployee($first);
    linkAttendanceMobileAuthEmployee($second);

    $this->postJson(
        'http://' . $second['domain'] . '/api/v1/auth/login',
        [
            'email' => 'mobile-auth-alpha@example.test',
            'password' => 'StrongPassword123!',
            'device_name' => 'Cross Tenant Device',
        ],
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('credentials');

    $this->assertDatabaseCount('personal_access_tokens', 0);
});


test('attendance API rejects missing tokens and tokens without the mobile ability', function () {
    $registration = createAttendanceMobileAuthTenant(
        'Mobile Auth Ability Guard',
        'mobile-auth-ability@example.test',
    );

    $url = 'http://' . $registration['domain'] . '/api/v1/attendance/policy';

    $this->getJson($url)->assertUnauthorized();

    $wrongAbilityToken = $registration['user']
        ->createToken('Wrong Ability Device', ['profile:read'])
        ->plainTextToken;

    $this->withToken($wrongAbilityToken)
        ->getJson($url)
        ->assertForbidden();
});

test('attendance mobile token accesses protected API and logout revokes only the current token', function () {
    $registration = createAttendanceMobileAuthTenant(
        'Mobile Auth Token Lifecycle',
        'mobile-auth-lifecycle@example.test',
    );

    linkAttendanceMobileAuthEmployee($registration);

    $token = $registration['user']
        ->createToken('Lifecycle Device', ['attendance:mobile']);

    $plainTextToken = $token->plainTextToken;
    $policyUrl = 'http://' . $registration['domain'] . '/api/v1/attendance/policy';
    $logoutUrl = 'http://' . $registration['domain'] . '/api/v1/auth/logout';

    $this->withToken($plainTextToken)
        ->getJson($policyUrl)
        ->assertOk()
        ->assertJsonStructure(['data' => ['mobile_checkin_enabled']]);

    $this->withToken($plainTextToken)
        ->postJson($logoutUrl)
        ->assertOk()
        ->assertJsonPath('message', 'Token revoked.');

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->accessToken->getKey(),
    ]);

    $this->withToken($plainTextToken)
        ->getJson($policyUrl)
        ->assertUnauthorized();
});
