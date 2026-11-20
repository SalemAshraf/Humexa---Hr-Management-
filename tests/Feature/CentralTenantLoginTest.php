<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
        'tenancy.central_domains' => ['humexa.test'],
        'session.domain' => '.humexa.test',
    ]);
});

test('company administrator can sign in from the central login and is redirected to their tenant dashboard', function () {
    $result = app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme Company',
        adminName: 'Acme Admin',
        email: 'admin@acme.test',
        password: 'StrongPassword123!'
    );

    $this->post('/login', [
        'email' => 'admin@acme.test',
        'password' => 'StrongPassword123!',
    ])
        ->assertRedirect('http://acme-company.humexa.test/dashboard')
        ->assertSessionHas('tenant_session_version', 1);

    expect(config('session.domain'))->toBe('.humexa.test')
        ->and($result['user']->fresh()->account_type)->toBe(User::ACCOUNT_TYPE_TENANT);
});


test('central company login uses an Inertia location response for cross-subdomain navigation', function () {
    app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme Company',
        adminName: 'Acme Admin',
        email: 'admin@acme.test',
        password: 'StrongPassword123!'
    );

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->post('/login', [
        'email' => 'admin@acme.test',
        'password' => 'StrongPassword123!',
    ])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'http://acme-company.humexa.test/dashboard')
        ->assertSessionHas('tenant_session_version', 1);
});

test('central company login rejects invalid credentials', function () {
    app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme Company',
        adminName: 'Acme Admin',
        email: 'admin@acme.test',
        password: 'StrongPassword123!'
    );

    $this->from('/login')
        ->post('/login', [
            'email' => 'admin@acme.test',
            'password' => 'WrongPassword123!',
        ])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email')
        ->assertSessionMissing('tenant_session_version');
});

test('central company login rejects suspended tenant workspaces', function () {
    $result = app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme Company',
        adminName: 'Acme Admin',
        email: 'admin@acme.test',
        password: 'StrongPassword123!'
    );

    $result['tenant']->forceFill([
        'status' => 'suspended',
        'suspended_at' => now(),
    ])->save();

    $this->from('/login')
        ->post('/login', [
            'email' => 'admin@acme.test',
            'password' => 'StrongPassword123!',
        ])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email')
        ->assertSessionMissing('tenant_session_version');
});
