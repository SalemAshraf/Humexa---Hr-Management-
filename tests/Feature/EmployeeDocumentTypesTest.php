<?php

declare(strict_types=1);

use App\\Modules\\Tenants\\Actions\\RegisterCompanyAction;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
        'tenancy.central_domains' => ['humexa.test'],
    ]);
});

function registerDocumentTypeTenant(string $company, string $email): array
{
    return app(RegisterCompanyAction::class)->execute(
        companyName: $company,
        adminName: $company . ' Admin',
        email: $email,
        password: 'StrongPassword123!',
    );
}

function actAsDocumentTypeTenant($test, array $registration): void
{
    $test->actingAs($registration['user'], 'tenant')
        ->withSession(['tenant_session_version' => 1]);
}

test('tenant can create list update deactivate and archive its document types', function () {
    $registration = registerDocumentTypeTenant('Document Types Alpha', 'document-types-alpha@example.test');
    actAsDocumentTypeTenant($this, $registration);

    $base = 'http://' . $registration['domain'] . '/employee-document-types';

    $this->post($base, [
        'name' => 'Employment Contract',
        'description' => 'Signed employment agreement',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $type = DB::table('employee_document_types')->first();

    expect($type->slug)->toBe('employment-contract')
        ->and((bool) $type->is_active)->toBeTrue();

    $this->getJson($base)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Employment Contract');

    $this->put($base . '/' . $type->id, [
        'name' => 'Updated Employment Contract',
        'description' => 'Updated description',
        'is_active' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('employee_document_types', [
        'id' => $type->id,
        'slug' => 'updated-employment-contract',
        'is_active' => false,
    ]);

    $this->delete($base . '/' . $type->id)->assertRedirect();

    $this->assertSoftDeleted('employee_document_types', ['id' => $type->id]);
    $this->getJson($base)->assertOk()->assertJsonCount(0, 'data');
});

test('tenant cannot access another tenants document types', function () {
    $owner = registerDocumentTypeTenant('Document Types Owner', 'document-types-owner@example.test');
    $intruder = registerDocumentTypeTenant('Document Types Intruder', 'document-types-intruder@example.test');

    actAsDocumentTypeTenant($this, $owner);
    $ownerBase = 'http://' . $owner['domain'] . '/employee-document-types';
    $this->post($ownerBase, ['name' => 'Confidential Contract'])->assertRedirect();

    $typeId = (int) DB::table('employee_document_types')
        ->where('tenant_id', $owner['tenant']->id)
        ->value('id');

    actAsDocumentTypeTenant($this, $intruder);
    $intruderBase = 'http://' . $intruder['domain'] . '/employee-document-types';

    $this->getJson($intruderBase)->assertOk()->assertJsonCount(0, 'data');
    $this->put($intruderBase . '/' . $typeId, [
        'name' => 'Tampered Type',
    ])->assertNotFound();
    $this->delete($intruderBase . '/' . $typeId)->assertNotFound();

    $this->assertDatabaseHas('employee_document_types', [
        'id' => $typeId,
        'name' => 'Confidential Contract',
        'tenant_id' => $owner['tenant']->id,
    ]);
});

test('document type validation rejects missing and oversized names', function () {
    $registration = registerDocumentTypeTenant('Document Types Validation', 'document-types-validation@example.test');
    actAsDocumentTypeTenant($this, $registration);

    $base = 'http://' . $registration['domain'] . '/employee-document-types';

    $this->post($base, ['name' => ''])->assertSessionHasErrors('name');
    $this->post($base, ['name' => str_repeat('x', 151)])->assertSessionHasErrors('name');

    $this->assertDatabaseCount('employee_document_types', 0);
});
