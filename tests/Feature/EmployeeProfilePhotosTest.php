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
        'employee.profile_photo_disk' => 'local',
    ]);
});

function registerEmployeePhotoTenant(string $company, string $email): array
{
    return app(RegisterCompanyAction::class)->execute(
        companyName: $company,
        adminName: $company . ' Admin',
        email: $email,
        password: 'StrongPassword123!',
    );
}

function actAsEmployeePhotoTenant($test, array $registration): void
{
    $test->actingAs($registration['user'], 'tenant')
        ->withSession(['tenant_session_version' => 1]);
}

function createEmployeePhotoTestEmployee($test, array $registration): int
{
    $test->post('http://' . $registration['domain'] . '/employees', [
        'first_name' => 'Photo',
        'last_name' => 'Employee',
        'employment_status' => 'draft',
    ])->assertRedirect()->assertSessionHasNoErrors();

    return (int) DB::table('employees')
        ->where('tenant_id', $registration['tenant']->id)
        ->latest('id')
        ->value('id');
}

function tinyEmployeePng(string $name = 'avatar.png'): UploadedFile
{
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=');

    return UploadedFile::fake()->createWithContent($name, $bytes);
}

test('profile photo is optional and tenant can upload view replace and remove it', function () {
    Storage::fake('local');

    $registration = registerEmployeePhotoTenant('Employee Photo Optional', 'employee-photo-optional@example.test');
    actAsEmployeePhotoTenant($this, $registration);

    $employeeId = createEmployeePhotoTestEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/profile-photo';

    expect(DB::table('employees')->where('id', $employeeId)->value('profile_photo_path'))->toBeNull();

    $this->post($base, ['photo' => tinyEmployeePng()])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $firstPath = DB::table('employees')->where('id', $employeeId)->value('profile_photo_path');

    expect($firstPath)->toBeString()
        ->and($firstPath)->toContain('tenants/' . $registration['tenant']->id . '/employees/' . $employeeId . '/profile/');

    Storage::disk('local')->assertExists($firstPath);

    $photoResponse = $this->get($base);

    $photoResponse
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertContent(Storage::disk('local')->get($firstPath));

    $this->post($base, ['photo' => tinyEmployeePng('replacement.png')])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $secondPath = DB::table('employees')->where('id', $employeeId)->value('profile_photo_path');

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('local')->assertMissing($firstPath);
    Storage::disk('local')->assertExists($secondPath);

    $this->delete($base)->assertRedirect()->assertSessionHasNoErrors();

    expect(DB::table('employees')->where('id', $employeeId)->value('profile_photo_path'))->toBeNull();
    Storage::disk('local')->assertMissing($secondPath);
    $this->get($base)->assertNotFound();
});

test('tenant cannot view or change another tenants employee profile photo', function () {
    Storage::fake('local');

    $owner = registerEmployeePhotoTenant('Employee Photo Owner', 'employee-photo-owner@example.test');
    actAsEmployeePhotoTenant($this, $owner);
    $employeeId = createEmployeePhotoTestEmployee($this, $owner);
    $ownerBase = 'http://' . $owner['domain'] . '/employees/' . $employeeId . '/profile-photo';

    $this->post($ownerBase, ['photo' => tinyEmployeePng()])->assertRedirect();
    $storedPath = DB::table('employees')->where('id', $employeeId)->value('profile_photo_path');

    $other = registerEmployeePhotoTenant('Employee Photo Other', 'employee-photo-other@example.test');
    actAsEmployeePhotoTenant($this, $other);
    // Attempt to address the owner's employee through the other tenant's domain.
    $otherBase = 'http://' . $other['domain'] . '/employees/' . $employeeId . '/profile-photo';

    $this->get($otherBase)->assertNotFound();
    $this->delete($otherBase)->assertNotFound();

    expect(DB::table('employees')->where('id', $employeeId)->value('profile_photo_path'))->toBe($storedPath);

    // The local disk root is tenant-suffixed, so switch back to the owner context
    // before asserting that the owner's private file still exists.
    tenancy()->end();
    tenancy()->initialize($owner['tenant']);

    Storage::disk('local')->assertExists($storedPath);
});

test('profile photo upload rejects unsupported and oversized files', function () {
    Storage::fake('local');

    $registration = registerEmployeePhotoTenant('Employee Photo Validation', 'employee-photo-validation@example.test');
    actAsEmployeePhotoTenant($this, $registration);
    $employeeId = createEmployeePhotoTestEmployee($this, $registration);
    $base = 'http://' . $registration['domain'] . '/employees/' . $employeeId . '/profile-photo';

    $this->post($base, [
        'photo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('photo');

    $this->post($base, [
        'photo' => UploadedFile::fake()->create('large.png', 5121, 'image/png'),
    ])->assertSessionHasErrors('photo');

    expect(DB::table('employees')->where('id', $employeeId)->value('profile_photo_path'))->toBeNull();
    expect(Storage::disk('local')->allFiles())->toBe([]);
});
