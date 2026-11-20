<?php

declare(strict_types=1);

use App\Modules\Tenants\Actions\RegisterCompanyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    config([
        'humexa.tenant_domain' => 'humexa.test',
        'humexa.reserved_subdomains' => [],
        'tenancy.central_domains' => ['humexa.test'],
        'employee.document_disk' => 'local',
    ]);
});

function registerEmployeeDocumentTenant(string $company, string $email): array
{
    return app(RegisterCompanyAction::class)->execute(
        companyName: $company,
        adminName: $company . ' Admin',
        email: $email,
        password: 'StrongPassword123!',
    );
}

function actAsEmployeeDocumentTenant($test, array $registration): void
{
    $test->actingAs($registration['user'], 'tenant')
        ->withSession(['tenant_session_version' => 1]);
}

function createEmployeeDocumentTestEmployee($test, array $registration): int
{
    $test->post('http://' . $registration['domain'] . '/employees', [
        'first_name' => 'Document',
        'last_name' => 'Employee',
        'employment_status' => 'draft',
    ])->assertRedirect()->assertSessionHasNoErrors();

    return (int) DB::table('employees')
        ->where('tenant_id', $registration['tenant']->id)
        ->latest('id')
        ->value('id');
}

function createEmployeeDocumentType($test, array $registration, string $name = 'Employment Contract'): int
{
    $test->post('http://' . $registration['domain'] . '/employee-document-types', [
        'name' => $name,
    ])->assertRedirect()->assertSessionHasNoErrors();

    return (int) DB::table('employee_document_types')
        ->where('tenant_id', $registration['tenant']->id)
        ->where('name', $name)
        ->value('id');
}

function employeeDocumentPayload(int $typeId, array $overrides = []): array
{
    return array_merge([
        'document_type_id' => $typeId,
        'title' => 'Signed Employment Contract',
        'file' => UploadedFile::fake()->create('employment-contract.pdf', 120, 'application/pdf'),
        'issue_date' => '2026-01-01',
        'expiry_date' => '2027-01-01',
        'notes' => 'Signed by both parties.',
    ], $overrides);
}

test('tenant can upload list preview and download a private employee document', function () {
    Storage::fake('local');

    $registration = registerEmployeeDocumentTenant('Employee Documents Alpha', 'employee-documents-alpha@example.test');
    actAsEmployeeDocumentTenant($this, $registration);

    $employeeId = createEmployeeDocumentTestEmployee($this, $registration);
    $typeId = createEmployeeDocumentType($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/documents';

    $this->post($base, employeeDocumentPayload($typeId))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $document = DB::table('employee_documents')->first();

    expect($document)->not->toBeNull()
        ->and($document->tenant_id)->toBe($registration['tenant']->id)
        ->and($document->employee_id)->toBe($employeeId)
        ->and($document->uploaded_by_user_id)->toBe($registration['user']->id)
        ->and($document->disk)->toBe('local')
        ->and($document->file_path)->toContain('tenants/' . $registration['tenant']->id . '/employees/' . $employeeId . '/documents/');

    Storage::disk('local')->assertExists($document->file_path);

    $this->getJson($base)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Signed Employment Contract')
        ->assertJsonPath('data.0.document_type_name', 'Employment Contract')
        ->assertJsonPath('data.0.is_expired', false)
        ->assertJsonMissingPath('data.0.file_path')
        ->assertJsonMissingPath('data.0.disk');

    $this->get($base . '/' . $document->id . '/preview')
        ->assertOk()
        ->assertHeader('Content-Security-Policy', 'sandbox')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->get($base . '/' . $document->id . '/download')
        ->assertOk()
        ->assertHeader('Content-Disposition');

    $this->delete('http://' . $registration['domain'] . '/employee-document-types/' . $typeId)
        ->assertRedirect();

    $this->getJson($base)
        ->assertOk()
        ->assertJsonPath('data.0.document_type_name', 'Employment Contract');

    $this->get($base . '/' . $document->id . '/download')->assertOk();
});

test('archiving a document preserves its metadata and private file for authorized access', function () {
    Storage::fake('local');

    $registration = registerEmployeeDocumentTenant('Employee Documents Archive', 'employee-documents-archive@example.test');
    actAsEmployeeDocumentTenant($this, $registration);

    $employeeId = createEmployeeDocumentTestEmployee($this, $registration);
    $typeId = createEmployeeDocumentType($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/documents';

    $this->post($base, employeeDocumentPayload($typeId))->assertRedirect();

    $document = DB::table('employee_documents')->first();

    $this->post($base . '/' . $document->id . '/archive')->assertRedirect();

    $this->assertDatabaseHas('employees', [
        'id' => $employeeId,
        'tenant_id' => $registration['tenant']->id,
        'deleted_at' => null,
    ]);

    $this->assertSoftDeleted('employee_documents', ['id' => $document->id]);
    Storage::disk('local')->assertExists($document->file_path);

    $this->getJson($base)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_archived', true);

    $this->get($base . '/' . $document->id . '/download')->assertOk();
});

test('tenant cannot access another tenants employee documents', function () {
    Storage::fake('local');

    $owner = registerEmployeeDocumentTenant('Employee Documents Owner', 'employee-documents-owner@example.test');
    $intruder = registerEmployeeDocumentTenant('Employee Documents Intruder', 'employee-documents-intruder@example.test');

    actAsEmployeeDocumentTenant($this, $owner);
    $ownerEmployeeId = createEmployeeDocumentTestEmployee($this, $owner);
    $ownerTypeId = createEmployeeDocumentType($this, $owner);
    $ownerBase = 'http://' . $owner['domain'] . '/employees/' . $ownerEmployeeId . '/documents';

    $this->post($ownerBase, employeeDocumentPayload($ownerTypeId))->assertRedirect();

    $documentId = (int) DB::table('employee_documents')
        ->where('tenant_id', $owner['tenant']->id)
        ->value('id');

    actAsEmployeeDocumentTenant($this, $intruder);
    $intruderEmployeeId = createEmployeeDocumentTestEmployee($this, $intruder);
    $intruderBase = 'http://' . $intruder['domain'] . '/employees/' . $intruderEmployeeId . '/documents';

    $this->getJson($intruderBase)->assertOk()->assertJsonCount(0, 'data');
    $this->get($intruderBase . '/' . $documentId . '/download')->assertNotFound();
    $this->get($intruderBase . '/' . $documentId . '/preview')->assertNotFound();
    $this->post($intruderBase . '/' . $documentId . '/archive')->assertNotFound();

    $this->assertDatabaseHas('employee_documents', [
        'id' => $documentId,
        'tenant_id' => $owner['tenant']->id,
    ]);
});

test('upload rejects inactive or foreign document types and invalid files or dates', function () {
    Storage::fake('local');

    $owner = registerEmployeeDocumentTenant('Employee Documents Validation', 'employee-documents-validation@example.test');
    $other = registerEmployeeDocumentTenant('Employee Documents Other', 'employee-documents-other@example.test');

    actAsEmployeeDocumentTenant($this, $owner);
    $employeeId = createEmployeeDocumentTestEmployee($this, $owner);
    $typeId = createEmployeeDocumentType($this, $owner);
    $base = 'http://' . $owner['domain'] . '/employees/' . $employeeId . '/documents';

    $this->put('http://' . $owner['domain'] . '/employee-document-types/' . $typeId, [
        'name' => 'Employment Contract',
        'is_active' => false,
    ])->assertRedirect();

    $this->post($base, employeeDocumentPayload($typeId))
        ->assertSessionHasErrors('document_type_id');

    actAsEmployeeDocumentTenant($this, $other);
    $otherTypeId = createEmployeeDocumentType($this, $other, 'Other Tenant Type');

    actAsEmployeeDocumentTenant($this, $owner);
    $this->post($base, employeeDocumentPayload($otherTypeId))
        ->assertSessionHasErrors('document_type_id');

    $this->post($base, employeeDocumentPayload($typeId, [
        'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        'issue_date' => '2026-06-01',
        'expiry_date' => '2026-05-01',
    ]))->assertSessionHasErrors(['document_type_id', 'file', 'expiry_date']);

    $this->assertDatabaseCount('employee_documents', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});
