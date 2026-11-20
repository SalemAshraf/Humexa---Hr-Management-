<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Models\TenantOwnedTestModel;
use App\Http\Middleware\EnsureTenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;



uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
    ]);

    Route::domain('{tenant}.humexa.test')
        ->middleware([
            'web',
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            'auth:tenant',
            EnsureTenantUser::class,
        ])
        ->get('/_test/protected', function () {
            return response('Protected tenant OK', 200);
        });

    $action = app(RegisterCompanyAction::class);

    $this->tenantA = $action->execute(
        companyName: 'Company Alpha',
        adminName: 'Alpha Admin',
        email: 'alpha@example.com',
        password: 'StrongPassword123!',
    )['tenant'];

    $this->tenantB = $action->execute(
        companyName: 'Company Beta',
        adminName: 'Beta Admin',
        email: 'beta@example.com',
        password: 'StrongPassword123!',
    )['tenant'];
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

test('it returns no tenant-owned records without an initialized tenant', function () {
    tenancy()->initialize($this->tenantA);

    TenantOwnedTestModel::create([
        'name' => 'Alpha Employee',
        'email' => 'employee-alpha@example.com',
        'password' => 'password',
    ]);

    tenancy()->end();

    tenancy()->initialize($this->tenantB);

    TenantOwnedTestModel::create([
        'name' => 'Beta Employee',
        'email' => 'employee-beta@example.com',
        'password' => 'password',
    ]);

    tenancy()->end();

    expect(TenantOwnedTestModel::query()->count())->toBe(0);
});

test('it only returns records belonging to the initialized tenant', function () {
    tenancy()->initialize($this->tenantA);

    TenantOwnedTestModel::create([
        'name' => 'Alpha Employee',
        'email' => 'employee-alpha@example.com',
        'password' => 'password',
    ]);

    tenancy()->end();

    tenancy()->initialize($this->tenantB);

    TenantOwnedTestModel::create([
        'name' => 'Beta Employee',
        'email' => 'employee-beta@example.com',
        'password' => 'password',
    ]);

    tenancy()->end();

    tenancy()->initialize($this->tenantA);

    $names = TenantOwnedTestModel::query()->pluck('name')->all();

expect($names)
    ->toContain('Alpha Admin')
    ->toContain('Alpha Employee')
    ->not->toContain('Beta Employee');
});

test('it replaces a supplied tenant id with the initialized tenant id', function () {

    tenancy()->initialize($this->tenantA);

    $record = TenantOwnedTestModel::create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Alpha Employee',
        'email' => 'employee-alpha@example.com',
        'password' => 'password',
    ]);

    expect($record->tenant_id)->toBe($this->tenantA->id);
});


test('it prevents bulk updates and deletes across tenants', function () {
    tenancy()->initialize($this->tenantB);

    $record = TenantOwnedTestModel::create([
        'name' => 'Beta Employee',
        'email' => 'beta-employee@example.com',
        'password' => 'password',
    ]);

    $recordId = $record->id;

    tenancy()->end();
    tenancy()->initialize($this->tenantA);

    $updated = TenantOwnedTestModel::whereKey($recordId)
        ->update(['name' => 'Hacked Employee']);

    $deleted = TenantOwnedTestModel::whereKey($recordId)->delete();

    expect($updated)->toBe(0)
        ->and($deleted)->toBe(0);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    expect(TenantOwnedTestModel::find($recordId)->name)
        ->toBe('Beta Employee');
});


test('it prevents updating a model outside its original tenant', function () {
    tenancy()->initialize($this->tenantB);

    $record = TenantOwnedTestModel::create([
        'name' => 'Beta Employee',
        'email' => 'beta-update@example.com',
        'password' => 'password',
    ]);

    tenancy()->end();
    tenancy()->initialize($this->tenantA);

    expect(fn () => $record->update([
        'name' => 'Hacked Employee',
    ]))->toThrow(\LogicException::class);
});


test('it prevents deleting a model outside its original tenant', function () {
    tenancy()->initialize($this->tenantB);

    $record = TenantOwnedTestModel::create([
        'name' => 'Beta Employee',
        'email' => 'beta-delete@example.com',
        'password' => 'password',
    ]);

    tenancy()->end();
    tenancy()->initialize($this->tenantA);

    expect(fn () => $record->delete())
        ->toThrow(\LogicException::class);
});



test('it prevents changing tenant id through bulk updates', function () {
    tenancy()->initialize($this->tenantA);

    $record = TenantOwnedTestModel::create([
        'name' => 'Alpha Employee',
        'email' => 'alpha-bulk@example.com',
        'password' => 'password',
    ]);

    expect(fn () => TenantOwnedTestModel::whereKey($record->id)
        ->update(['tenant_id' => $this->tenantB->id]))
        ->toThrow(\LogicException::class);
});

test('it prevents quietly updating a model outside its original tenant', function () {
    tenancy()->initialize($this->tenantB);

    $record = TenantOwnedTestModel::create([
        'name' => 'Beta Quiet Update',
        'email' => 'beta-quiet-update@example.com',
        'password' => 'password',
    ]);

    tenancy()->end();
    tenancy()->initialize($this->tenantA);

    expect(fn () => $record->updateQuietly([
        'name' => 'Hacked Quiet Update',
    ]))->toThrow(\LogicException::class);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    expect(TenantOwnedTestModel::find($record->id)->name)
        ->toBe('Beta Quiet Update');
});

test('it prevents quietly deleting a model outside its original tenant', function () {
    tenancy()->initialize($this->tenantB);

    $record = TenantOwnedTestModel::create([
        'name' => 'Beta Quiet Delete',
        'email' => 'beta-quiet-delete@example.com',
        'password' => 'password',
    ]);

    tenancy()->end();
    tenancy()->initialize($this->tenantA);

    expect(fn () => $record->deleteQuietly())
        ->toThrow(\LogicException::class);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    expect(TenantOwnedTestModel::find($record->id))
        ->not->toBeNull();
});

test('it prevents changing tenant id through saveQuietly', function () {
    tenancy()->initialize($this->tenantA);

    $record = TenantOwnedTestModel::create([
        'name' => 'Alpha Employee',
        'email' => 'alpha-quiet-tenant@example.com',
        'password' => 'password',
    ]);

    $record->tenant_id = $this->tenantB->id;

    expect(fn () => $record->saveQuietly())
        ->toThrow(\LogicException::class);

    tenancy()->end();
    tenancy()->initialize($this->tenantA);

    $freshRecord = TenantOwnedTestModel::find($record->id);

    expect($freshRecord)->not->toBeNull()
        ->and($freshRecord->tenant_id)->toBe($this->tenantA->id);
});

test('it allows quietly updating a model within its own tenant', function () {
    tenancy()->initialize($this->tenantA);

    $record = TenantOwnedTestModel::create([
        'name' => 'Alpha Employee',
        'email' => 'alpha-normal-quiet@example.com',
        'password' => 'password',
    ]);

    $updated = $record->updateQuietly([
        'name' => 'Alpha Employee Updated',
    ]);

    expect($updated)->toBeTrue()
        ->and($record->fresh()->name)->toBe('Alpha Employee Updated');
});

test('it prevents incrementing a model outside its original tenant', function () {
    tenancy()->initialize($this->tenantB);

    $record = TenantOwnedTestModel::create([
        'name' => 'Beta Employee',
        'email' => 'beta-increment@example.com',
        'password' => 'password',
    ]);

    $recordId = $record->id;

    tenancy()->end();
    tenancy()->initialize($this->tenantA);

    expect(fn () => $record->increment('id'))
        ->toThrow(\LogicException::class);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    expect(TenantOwnedTestModel::find($recordId))->not->toBeNull();
});

test('it prevents quietly incrementing a model outside its original tenant', function () {
    tenancy()->initialize($this->tenantB);

    $record = TenantOwnedTestModel::create([
        'name' => 'Beta Employee',
        'email' => 'beta-increment-quiet@example.com',
        'password' => 'password',
    ]);

    $recordId = $record->id;

    tenancy()->end();
    tenancy()->initialize($this->tenantA);

    expect(fn () => $record->incrementQuietly('id'))
        ->toThrow(\LogicException::class);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    expect(TenantOwnedTestModel::find($recordId))->not->toBeNull();
});

test('tenant user can access protected route through real domain resolution', function () {
    $result = app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme Company',
        adminName: 'John Admin',
        email: 'john@acme.com',
        password: 'StrongPassword123!',
    );

    $user = User::where('email', 'john@acme.com')->firstOrFail();

    $this->actingAs($user, 'tenant')
        ->get('http://acme-company.humexa.test/_test/protected')
        ->assertOk()
        ->assertSee('Protected tenant OK');
});

test('tenant user cannot access another tenant through real domain resolution', function () {
    $alphaUser = User::where('email', 'alpha@example.com')
        ->where('tenant_id', $this->tenantA->id)
        ->firstOrFail();

    $betaDomain = $this->tenantB->domains()->firstOrFail()->domain;

    $this->actingAs($alphaUser, 'tenant')
        ->get("http://{$betaDomain}/_test/protected")
        ->assertForbidden();
});

test('guest cannot access protected tenant route through real domain resolution', function () {
    app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme Company',
        adminName: 'John Admin',
        email: 'john@acme.com',
        password: 'StrongPassword123!',
    );

    $this->get('http://acme-company.humexa.test/_test/protected')
        ->assertRedirect();
});

test('platform user cannot access protected tenant route through real domain resolution', function () {
    app(RegisterCompanyAction::class)->execute(
        companyName: 'Acme Company',
        adminName: 'John Admin',
        email: 'john@acme.com',
        password: 'StrongPassword123!',
    );

    $platformUser = User::factory()->create();

    $platformUser->forceFill([
        'tenant_id' => null,
        'account_type' => User::ACCOUNT_TYPE_PLATFORM,
    ])->save();

    $this->actingAs($platformUser, 'tenant')
        ->get('http://acme-company.humexa.test/_test/protected')
        ->assertForbidden();
});





