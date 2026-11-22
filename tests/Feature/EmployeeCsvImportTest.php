<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Employees\Actions\CreateEmployeeAction;
use App\Modules\Employees\Models\Employee;
use App\Modules\Organization\Models\Department;
use Illuminate\Http\UploadedFile;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);

    $this->reg = app(RegisterCompanyAction::class)->execute(companyName: 'Me Co', adminName: 'Me Owner', email: 'me-owner@tenant-test.example', password: 'StrongPassword123!');
    $this->tid = (string) $this->reg['tenant']->id;
    $this->base = 'http://' . $this->reg['domain'];
    $this->as = fn (User $u) => $this->actingAs($u, 'tenant')->withSession(['tenant_session_version' => 1]);

    $this->account = function (string $email, ?string $first = null) {
        $u = $this->reg['tenant']->run(function () use ($email) {
            $u = new User(['name' => $email, 'email' => $email, 'password' => 'StrongPassword123!']);
            $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
            $u->assignRole(TenantRoleCatalog::EMPLOYEE);

            return $u;
        });
        $emp = null;
        if ($first) {
            $emp = app(CreateEmployeeAction::class)->execute(data: ['first_name' => $first, 'last_name' => 'Self', 'employment_status' => 'active', 'hire_date' => '2026-01-05', 'user_id' => $u->id], tenantId: $this->tid, actorId: null);
        }

        return [$u, $emp];
    };

    $this->annual = LeaveType::create(['tenant_id' => $this->tid, 'name' => 'Annual', 'annual_days' => 10, 'is_paid' => true, 'is_active' => true]);
});


function csv(string $text): UploadedFile
{
    return UploadedFile::fake()->createWithContent('staff.csv', $text);
}

test('a valid CSV is checked first and only saved on commit', function () {
    Department::query()->forceCreate(['tenant_id' => $this->tid, 'name' => 'Finance']);
    $owner = $this->reg['user'];
    $text = "first_name,last_name,work_email,phone,hire_date,department,position,employee_number\nSara,Hassan,sara@x.example,0100,2026-01-15,finance,,\nAli,Samy,,,2026-02-01,,,\n";

    ($this->as)($owner)->post("{$this->base}/employees/import", ['file' => csv($text), 'commit' => 0])
        ->assertSessionHas('import_report', fn ($r) => $r['valid'] === 2 && $r['errors'] === [] && $r['imported'] === false);
    expect(Employee::where('tenant_id', $this->tid)->count())->toBe(0);

    ($this->as)($owner)->post("{$this->base}/employees/import", ['file' => csv($text), 'commit' => 1])
        ->assertSessionHas('import_report', fn ($r) => $r['imported'] === true);
    expect(Employee::where('tenant_id', $this->tid)->count())->toBe(2)
        ->and(Employee::where('work_email', 'sara@x.example')->value('department_id'))->not->toBeNull();
});

test('one bad row blocks the whole import and is reported by line', function () {
    $owner = $this->reg['user'];
    $text = "first_name,last_name,work_email,hire_date,department\nSara,Hassan,sara@x.example,2026-01-15,\nBad,Row,not-an-email,15/01/2026,Nowhere\nDup,Mail,sara@x.example,2026-01-15,\n";

    ($this->as)($owner)->post("{$this->base}/employees/import", ['file' => csv($text), 'commit' => 1])
        ->assertSessionHas('import_report', function ($r) {
            $lines = collect($r['errors'])->pluck('line')->all();

            return $r['imported'] === false && $lines === [3, 4] && $r['valid'] === 1;
        });
    expect(Employee::where('tenant_id', $this->tid)->count())->toBe(0);
});

test('missing columns, empty files and a plan limit are refused with a message', function () {
    $owner = $this->reg['user'];

    ($this->as)($owner)->post("{$this->base}/employees/import", ['file' => csv("name\nSara\n")])
        ->assertSessionHas('import_report', fn ($r) => isset($r['error']));
    ($this->as)($owner)->post("{$this->base}/employees/import", ['file' => csv("first_name,last_name,hire_date\n")])
        ->assertSessionHas('import_report', fn ($r) => isset($r['error']));

    $plan = \App\Modules\Plans\Models\Plan::query()->create(['name' => 'Tiny', 'max_employees' => 1, 'max_accounts' => 5, 'is_active' => true]);
    {
        $plan->forceFill(['max_employees' => 1])->save();
        \App\Models\Tenant::query()->whereKey($this->tid)->update(['plan_id' => $plan->id]);
        ($this->as)($owner)->post("{$this->base}/employees/import", ['file' => csv("first_name,last_name,hire_date\nA,B,2026-01-01\nC,D,2026-01-02\n"), 'commit' => 1])
            ->assertSessionHas('import_report', fn ($r) => $r['plan_block'] !== null && $r['imported'] === false);
        expect(Employee::where('tenant_id', $this->tid)->count())->toBe(0);
    }
});

test('only HR can import and the template downloads', function () {
    [$u] = ($this->account)('imp@tenant-test.example', 'Imo');
    ($this->as)($u)->post("{$this->base}/employees/import", ['file' => csv("first_name,last_name,hire_date\nA,B,2026-01-01\n")])->assertForbidden();
    ($this->as)($u)->get("{$this->base}/employees/import")->assertForbidden();
    ($this->as)($this->reg['user'])->get("{$this->base}/employees/import")->assertOk();
    ($this->as)($this->reg['user'])->get("{$this->base}/employees/import/template")->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
