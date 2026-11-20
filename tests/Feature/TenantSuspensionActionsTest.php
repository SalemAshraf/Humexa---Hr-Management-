<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Tenants\Actions\ActivateTenantAction;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Tenants\Actions\SuspendTenantAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
    ]);

    $registration = app(RegisterCompanyAction::class)->execute(
        companyName: 'Suspension Test Company',
        adminName: 'Company Admin',
        email: 'admin@suspension-test.example',
        password: 'StrongPassword123!',
    );

    $this->tenant = $registration['tenant'];
    $this->admin = $registration['user'];
});

test('suspending a tenant changes its status and increments its session version', function () {
    $tenant = app(SuspendTenantAction::class)->execute((string) $this->tenant->getKey());

    expect($tenant->getAttribute('status'))->toBe('suspended')
        ->and((int) $tenant->getAttribute('session_version'))->toBe(2)
        ->and($tenant->getAttribute('suspended_at'))->not->toBeNull();
});

test('suspending an already suspended tenant does not increment its session version again', function () {
    $action = app(SuspendTenantAction::class);

    $first = $action->execute((string) $this->tenant->getKey());
    $second = $action->execute((string) $this->tenant->getKey());

    expect((int) $first->getAttribute('session_version'))->toBe(2)
        ->and((int) $second->getAttribute('session_version'))->toBe(2)
        ->and($second->getAttribute('status'))->toBe('suspended');
});

test('activating a suspended tenant preserves the incremented session version and retained data', function () {
    app(SuspendTenantAction::class)->execute((string) $this->tenant->getKey());

    $tenant = app(ActivateTenantAction::class)->execute((string) $this->tenant->getKey());

    expect($tenant->getAttribute('status'))->toBe('active')
        ->and((int) $tenant->getAttribute('session_version'))->toBe(2)
        ->and($tenant->getAttribute('suspended_at'))->toBeNull()
        ->and(User::query()->whereKey($this->admin->getKey())->exists())->toBeTrue()
        ->and(Tenant::query()->whereKey($this->tenant->getKey())->exists())->toBeTrue()
        ->and($tenant->domains()->exists())->toBeTrue();
});

test('activating an already active tenant is idempotent', function () {
    $tenant = app(ActivateTenantAction::class)->execute((string) $this->tenant->getKey());

    expect($tenant->getAttribute('status'))->toBe('active')
        ->and((int) $tenant->getAttribute('session_version'))->toBe(1)
        ->and($tenant->getAttribute('suspended_at'))->toBeNull();
});
