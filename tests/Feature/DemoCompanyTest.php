<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Employees\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function demoPwFile(string $pw): string
{
    $f = tempnam(sys_get_temp_dir(), 'pw');
    file_put_contents($f, $pw."\n");

    return $f;
}

it('creates one fictional demo company additively and refuses to run twice', function () {
    $before = Tenant::count();
    $f = demoPwFile('Sample-Pass-123456');

    $this->artisan('humexa:demo-company', ['--email' => 'owner@demo.example.test', '--password-file' => $f])->assertFailed(); // no --confirm
    expect(Tenant::count())->toBe($before);

    $this->artisan('humexa:demo-company', ['--email' => 'owner@demo.example.test', '--password-file' => $f, '--confirm' => true])->assertSuccessful();
    tenancy()->end();

    $tenant = Tenant::query()->get()->firstWhere('company_name', 'Demo Company (sample data)');
    expect(Tenant::count())->toBe($before + 1);
    expect($tenant->subscription_ends_at)->not->toBeNull();
    $u = User::query()->where('email', 'owner@demo.example.test')->firstOrFail();
    expect($u->tenant_id)->toBe((string) $tenant->id)->and(Hash::check('Sample-Pass-123456', $u->password))->toBeTrue();
    expect($u->account_type)->toBe(User::ACCOUNT_TYPE_TENANT);
    expect(Employee::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThan(5);
    expect(AttendanceRecord::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThan(20);

    $this->artisan('humexa:demo-company', ['--email' => 'owner@demo.example.test', '--password-file' => $f, '--confirm' => true])->assertFailed();
    expect(Tenant::count())->toBe($before + 1);
    @unlink($f);
});

it('rejects a short password and an unreadable file', function () {
    $this->artisan('humexa:demo-company', ['--email' => 'a@demo.example.test', '--password-file' => demoPwFile('short'), '--confirm' => true])->assertFailed();
    $this->artisan('humexa:demo-company', ['--email' => 'a@demo.example.test', '--password-file' => '/nonexistent', '--confirm' => true])->assertFailed();
    expect(User::query()->where('email', 'a@demo.example.test')->exists())->toBeFalse();
});
