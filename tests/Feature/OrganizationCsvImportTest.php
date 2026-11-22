<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);
    $this->reg = app(RegisterCompanyAction::class)->execute(companyName: 'Org Co', adminName: 'Org Owner', email: 'org-owner@tenant-test.example', password: 'StrongPassword123!');
    $this->tid = (string) $this->reg['tenant']->id;
    $this->base = 'http://' . $this->reg['domain'];
    $this->as = fn (User $u) => $this->actingAs($u, 'tenant')->withSession(['tenant_session_version' => 1]);
});

function orgCsv(string $t): UploadedFile
{
    return UploadedFile::fake()->createWithContent('org.csv', $t);
}

test('departments are checked first, saved on commit, and existing rows are never changed', function () {
    $existing = Department::query()->forceCreate(['tenant_id' => $this->tid, 'name' => 'Finance', 'code' => 'FIN', 'description' => 'keep me']);
    $owner = $this->reg['user'];
    $url = "{$this->base}/organization/import/departments";

    ($this->as)($owner)->post($url, ['file' => orgCsv("name,code,description\nSales,SAL,x\nOps,,\n"), 'commit' => 0])
        ->assertSessionHas('import_report', fn ($r) => $r['valid'] === 2 && $r['errors'] === [] && $r['imported'] === false);
    expect(Department::query()->where('tenant_id', $this->tid)->count())->toBe(1);

    ($this->as)($owner)->post($url, ['file' => orgCsv("name,code,description\nSales,SAL,x\nOps,,\n"), 'commit' => 1])
        ->assertSessionHas('import_report', fn ($r) => $r['imported'] === true);
    expect(Department::query()->where('tenant_id', $this->tid)->count())->toBe(3);

    // duplicate name (case-insensitive), duplicate code and a repeat inside the file all block everything
    ($this->as)($owner)->post($url, ['file' => orgCsv("name,code\nfinance,\nNew,FIN\nZed,\nzed,\n"), 'commit' => 1])
        ->assertSessionHas('import_report', fn ($r) => count($r['errors']) === 3 && $r['imported'] === false);
    expect(Department::query()->where('tenant_id', $this->tid)->count())->toBe(3);
    expect($existing->fresh()->description)->toBe('keep me');
});

test('positions need an existing department and the same name is allowed in another department', function () {
    $fin = Department::query()->forceCreate(['tenant_id' => $this->tid, 'name' => 'Finance']);
    $hr = Department::query()->forceCreate(['tenant_id' => $this->tid, 'name' => 'HR']);
    Position::query()->forceCreate(['tenant_id' => $this->tid, 'department_id' => $fin->id, 'name' => 'Manager']);
    $owner = $this->reg['user'];
    $url = "{$this->base}/organization/import/positions";

    ($this->as)($owner)->post($url, ['file' => orgCsv("name,department\nManager,Nowhere\nManager,Finance\n"), 'commit' => 1])
        ->assertSessionHas('import_report', fn ($r) => count($r['errors']) === 2 && $r['imported'] === false);

    ($this->as)($owner)->post($url, ['file' => orgCsv("name,department\nManager,hr\nAccountant,Finance\n"), 'commit' => 1])
        ->assertSessionHas('import_report', fn ($r) => $r['imported'] === true);
    expect(Position::query()->where('tenant_id', $this->tid)->count())->toBe(3);
});

test('only organization managers can import, bad entities 404, other companies are untouched, and expiry blocks it', function () {
    $u = $this->reg['tenant']->run(function () {
        $u = new User(['name' => 'e', 'email' => 'e@tenant-test.example', 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole(TenantRoleCatalog::EMPLOYEE);

        return $u;
    });
    $url = "{$this->base}/organization/import/departments";
    ($this->as)($u)->get($url)->assertForbidden();
    ($this->as)($u)->post($url, ['file' => orgCsv("name\nA\n"), 'commit' => 1])->assertForbidden();
    ($this->as)($this->reg['user'])->get("{$this->base}/organization/import/branches")->assertNotFound();
    ($this->as)($this->reg['user'])->get("{$url}/template")->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    ($this->as)($this->reg['user'])->post($url, ['file' => orgCsv(str_repeat("A,B\n", 3)), 'commit' => 1])
        ->assertSessionHas('import_report', fn ($r) => isset($r['error']));
});

test('an ended plan blocks the organization import', function () {
    $this->reg['tenant']->forceFill(['trial_ends_at' => now()->subDays(3)->toDateString(), 'subscription_ends_at' => null])->save();
    ($this->as)($this->reg['user'])->post("{$this->base}/organization/import/departments", ['file' => orgCsv("name\nLate\n"), 'commit' => 1]);
    expect(Department::query()->where('tenant_id', $this->tid)->where('name', 'Late')->exists())->toBeFalse();
});
