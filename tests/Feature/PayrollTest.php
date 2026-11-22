<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Payroll\Models\PayrollRuleSet;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);

    $this->reg = app(RegisterCompanyAction::class)->execute(companyName: 'Pay Co', adminName: 'Pay Owner', email: 'pay-owner@tenant-test.example', password: 'StrongPassword123!');
    $this->tid = (string) $this->reg['tenant']->id;
    $this->base = 'http://'.$this->reg['domain'];
    $this->mk = fn (string $role, string $email) => $this->reg['tenant']->run(function () use ($role, $email) {
        $u = new User(['name' => $email, 'email' => $email, 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole($role);

        return $u;
    });
    $this->as = fn (User $u) => $this->actingAs($u, 'tenant')->withSession(['tenant_session_version' => 1]);
    $this->hr = ($this->mk)(TenantRoleCatalog::HR_ADMIN, 'hr-pay@tenant-test.example');
    $this->worker = Employee::query()->forceCreate([
        'tenant_id' => $this->tid, 'employee_number' => 'PY-1', 'first_name' => 'Salary', 'last_name' => 'Earner',
        'employment_status' => 'active', 'hire_date' => '2024-01-01',
    ]);
});

test('a run needs confirmed rules, then calculates payslips and locks on approval', function () {
    $hr = $this->hr;
    ($this->as)($hr)->get("{$this->base}/payroll")->assertOk();
    expect(PayrollRuleSet::where('tenant_id', $this->tid)->count())->toBe(0); // opening the page saves nothing
    ($this->as)($hr)->put("{$this->base}/payroll/rules", ['name' => 'Blank', 'effective_from' => '2026-01-01', 'insurance_employee_rate' => 0, 'insurance_employer_rate' => 0,
        'insurable_min' => 0, 'insurable_max' => 0, 'personal_exemption_annual' => 0, 'rounding_step' => 10, 'after_tax_levy_rate' => 0, 'month_days_basis' => 30,
        'tax_tables_json' => json_encode([['up_to' => null, 'bands' => [[null, 0]]]])])->assertSessionHasNoErrors();
    PayrollRuleSet::where('tenant_id', $this->tid)->update(['insurance_employee_rate' => null, 'insurance_employer_rate' => null, 'insurable_min' => null, 'insurable_max' => null, 'personal_exemption_annual' => null, 'tax_tables' => '[]']);
    $rules = PayrollRuleSet::where('tenant_id', $this->tid)->firstOrFail();
    expect($rules->isConfirmed())->toBeFalse()->and($rules->missingSettings())->toHaveCount(6);

    // A fresh company has no statutory number pre-filled, and blank rules cannot be confirmed.
    ($this->as)($hr)->post("{$this->base}/payroll/rules/confirm")->assertSessionHasErrors('rules');
    expect($rules->fresh()->isConfirmed())->toBeFalse();
    ($this->as)($hr)->post("{$this->base}/payroll/runs", ['year' => 2026, 'month' => 11])->assertSessionHasErrors('run');

    ($this->as)($hr)->put("{$this->base}/payroll/rules", ['name' => 'Test', 'effective_from' => '2026-01-01', 'insurance_employee_rate' => 0.11, 'insurance_employer_rate' => 0.1875,
        'insurable_min' => 2700, 'insurable_max' => 16700, 'personal_exemption_annual' => 20000, 'rounding_step' => 10, 'after_tax_levy_rate' => 0, 'month_days_basis' => 30,
        'tax_tables_json' => json_encode(payrollTestTaxTables())])->assertSessionHasNoErrors();

    ($this->as)($hr)->post("{$this->base}/payroll/employees/{$this->worker->id}/compensation", ['effective_from' => '2026-01-01', 'basic_salary' => 30000, 'allowances' => 0, 'insured' => true])->assertRedirect();
    ($this->as)($hr)->post("{$this->base}/payroll/rules/confirm")->assertRedirect();
    ($this->as)($hr)->post("{$this->base}/payroll/runs", ['year' => 2026, 'month' => 11])->assertRedirect();

    $run = PayrollRun::where('tenant_id', $this->tid)->firstOrFail();
    $slip = Payslip::where('payroll_run_id', $run->id)->firstOrFail();
    expect((float) $slip->net_pay)->toBe(23472.27)->and((float) $slip->income_tax)->toBe(4690.73);

    ($this->as)($hr)->post("{$this->base}/payroll/runs", ['year' => 2026, 'month' => 11])->assertSessionHasErrors('run');

    ($this->as)($hr)->post("{$this->base}/payroll/runs/{$run->id}/adjustments", ['employee_id' => $this->worker->id, 'kind' => 'deduction', 'label' => 'Advance', 'amount' => 1000, 'taxable' => false])->assertRedirect();
    expect((float) Payslip::where('payroll_run_id', $run->id)->first()->net_pay)->toBe(22472.27);

    ($this->as)($hr)->get("{$this->base}/payroll/runs/{$run->id}/export")->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertDownload('payroll-run-2026-11.csv');
    // The creator cannot approve their own run while another payroll manager exists.
    ($this->as)($hr)->post("{$this->base}/payroll/runs/{$run->id}/approve")->assertSessionHasErrors('run');
    expect($run->fresh()->status)->toBe('draft');
    ($this->as)($this->reg['user'])->post("{$this->base}/payroll/runs/{$run->id}/approve")->assertSessionHasNoErrors();
    expect($run->fresh()->status)->toBe('approved');

    // The approved run keeps the parameters it was calculated with, even if the rules change later.
    $before = Payslip::where('payroll_run_id', $run->id)->first();
    PayrollRuleSet::where('tenant_id', $this->tid)->update(['insurance_employee_rate' => 0.5, 'insurable_max' => 99999]);
    $after = Payslip::where('payroll_run_id', $run->id)->first();
    expect($after->details['rules_snapshot']['insurance_employee_rate'])->toEqual(0.11)->and($after->details['rules_snapshot']['insurable_max'])->toEqual(16700)
        ->and((float) $after->net_pay)->toBe((float) $before->net_pay);
    ($this->as)($hr)->post("{$this->base}/payroll/runs/{$run->id}/recalculate")->assertStatus(422);
    ($this->as)($hr)->delete("{$this->base}/payroll/runs/{$run->id}")->assertStatus(422);
    ($this->as)($hr)->post("{$this->base}/payroll/runs/{$run->id}/adjustments", ['employee_id' => $this->worker->id, 'kind' => 'earning', 'label' => 'x', 'amount' => 5, 'taxable' => true])->assertStatus(422);
});

test('editing confirmed rules clears the confirmation and bad tables are refused', function () {
    $hr = $this->hr;
    ($this->as)($hr)->get("{$this->base}/payroll")->assertOk();
    $r = PayrollRuleSet::query()->forceCreate(['tenant_id' => $this->tid] + payrollTestRules());
    ($this->as)($hr)->post("{$this->base}/payroll/rules/confirm");
    expect($r->fresh()->isConfirmed())->toBeTrue();

    $payload = fn (array $o = []) => array_merge([
        'name' => 'Mine', 'effective_from' => '2026-01-01', 'insurance_employee_rate' => 0.11, 'insurance_employer_rate' => 0.1875,
        'insurable_min' => 2700, 'insurable_max' => 16700, 'personal_exemption_annual' => 20000, 'rounding_step' => 10,
        'after_tax_levy_rate' => 0, 'month_days_basis' => 30,
        'tax_tables_json' => json_encode([['up_to' => null, 'bands' => [[50000, 0], [null, 0.2]]]]),
    ], $o);

    ($this->as)($hr)->put("{$this->base}/payroll/rules", $payload(['tax_tables_json' => json_encode([['up_to' => 5, 'bands' => [[null, 0.1]]]])]))->assertStatus(422);
    expect($r->fresh()->isConfirmed())->toBeTrue();

    ($this->as)($hr)->put("{$this->base}/payroll/rules", $payload())->assertRedirect();
    expect($r->fresh()->isConfirmed())->toBeFalse()->and($r->fresh()->name)->toBe('Mine');
});

test('roles without payroll access are refused, and other tenants cannot be reached', function () {
    $emp = ($this->mk)(TenantRoleCatalog::EMPLOYEE, 'emp-pay@tenant-test.example');
    $mgr = ($this->mk)(TenantRoleCatalog::MANAGER, 'mgr-pay@tenant-test.example');

    foreach ([$emp, $mgr] as $u) {
        ($this->as)($u)->get("{$this->base}/payroll")->assertForbidden();
        ($this->as)($u)->post("{$this->base}/payroll/runs", ['year' => 2026, 'month' => 1])->assertForbidden();
    }

    $other = app(RegisterCompanyAction::class)->execute(companyName: 'Other Co', adminName: 'O', email: 'other-pay@tenant-test.example', password: 'StrongPassword123!');
    $foreign = PayrollRun::query()->forceCreate(['tenant_id' => (string) $other['tenant']->id, 'year' => 2026, 'month' => 1,
        'rule_set_id' => PayrollRuleSet::query()->forceCreate(['tenant_id' => (string) $other['tenant']->id] + payrollTestRules())->id]);

    ($this->as)($this->hr)->get("{$this->base}/payroll/runs/{$foreign->id}")->assertNotFound();
    ($this->as)($this->hr)->post("{$this->base}/payroll/runs/{$foreign->id}/approve")->assertNotFound();
    expect($foreign->fresh()->status)->toBe('draft');
});

test('a run uses the rule set in force for its month and keeps a snapshot', function () {
    $hr = $this->hr;
    ($this->as)($hr)->get("{$this->base}/payroll")->assertOk();
    $old = PayrollRuleSet::query()->forceCreate(['tenant_id' => $this->tid] + payrollTestRules());
    $old->forceFill(['effective_from' => '2025-01-01', 'confirmed_at' => now(), 'insurable_max' => 12000])->save();
    $new = PayrollRuleSet::query()->forceCreate(['tenant_id' => $this->tid] + payrollTestRules() + []);
    $new->forceFill(['effective_from' => '2026-06-01', 'confirmed_at' => now()])->save();

    ($this->as)($hr)->post("{$this->base}/payroll/employees/{$this->worker->id}/compensation", ['effective_from' => '2025-01-01', 'basic_salary' => 30000, 'allowances' => 0, 'insured' => true]);
    ($this->as)($hr)->post("{$this->base}/payroll/runs", ['year' => 2025, 'month' => 3])->assertRedirect();
    ($this->as)($hr)->post("{$this->base}/payroll/runs", ['year' => 2026, 'month' => 7])->assertRedirect();
    ($this->as)($hr)->post("{$this->base}/payroll/runs", ['year' => 2024, 'month' => 12])->assertSessionHasErrors('run');

    $early = Payslip::whereHas('run', fn ($q) => $q->where('year', 2025))->firstOrFail();
    $late = Payslip::whereHas('run', fn ($q) => $q->where('year', 2026))->firstOrFail();
    expect((float) $early->insurable_wage)->toBe(12000.0)->and((float) $late->insurable_wage)->toBe(16700.0)
        ->and($early->details['rules_snapshot']['insurable_max'])->toEqual(12000)->and($early->details['estimate_only'])->toBeTrue();

    $old->forceFill(['insurable_max' => 99999])->save();
    expect($early->fresh()->details['rules_snapshot']['insurable_max'])->toEqual(12000);
});


test('an advance is deducted in installments only from approved runs, never beyond the balance, and cancelling stops it', function () {
    $hr = $this->hr;
    ($this->as)($hr)->put("{$this->base}/payroll/rules", ['name' => 'Test', 'effective_from' => '2026-01-01', 'insurance_employee_rate' => 0.11, 'insurance_employer_rate' => 0.1875,
        'insurable_min' => 2700, 'insurable_max' => 16700, 'personal_exemption_annual' => 20000, 'rounding_step' => 10, 'after_tax_levy_rate' => 0, 'month_days_basis' => 30,
        'tax_tables_json' => json_encode(payrollTestTaxTables())])->assertSessionHasNoErrors();
    ($this->as)($hr)->post("{$this->base}/payroll/employees/{$this->worker->id}/compensation", ['effective_from' => '2026-01-01', 'basic_salary' => 30000, 'allowances' => 0, 'insured' => true, 'bank_name' => 'Test Bank', 'bank_account' => 'EG00TEST'])->assertSessionHasNoErrors();
    ($this->as)($hr)->post("{$this->base}/payroll/rules/confirm")->assertRedirect();

    ($this->as)($hr)->post("{$this->base}/payroll/advances", ['employee_id' => $this->worker->id, 'principal' => 1500, 'installment' => 1000, 'start_year' => 2026, 'start_month' => 11])->assertSessionHasNoErrors();
    ($this->as)($hr)->post("{$this->base}/payroll/advances", ['employee_id' => $this->worker->id, 'principal' => 100, 'installment' => 500, 'start_year' => 2026, 'start_month' => 11])->assertSessionHasErrors('installment');

    // Before the start month nothing is taken.
    ($this->as)($hr)->post("{$this->base}/payroll/runs", ['year' => 2026, 'month' => 10])->assertRedirect();
    $oct = PayrollRun::where('tenant_id', $this->tid)->where('month', 10)->firstOrFail();
    expect((float) Payslip::where('payroll_run_id', $oct->id)->first()->adjustments_deductions)->toBe(0.0);

    ($this->as)($hr)->post("{$this->base}/payroll/runs", ['year' => 2026, 'month' => 11])->assertRedirect();
    $nov = PayrollRun::where('tenant_id', $this->tid)->where('month', 11)->firstOrFail();
    expect((float) Payslip::where('payroll_run_id', $nov->id)->first()->net_pay)->toBe(22472.27);
    $advance = \App\Modules\Payroll\Models\EmployeeAdvance::where('tenant_id', $this->tid)->firstOrFail();
    expect($advance->balance())->toBe(1500.0); // a draft run takes nothing yet

    ($this->as)($this->reg['user'])->post("{$this->base}/payroll/runs/{$nov->id}/approve")->assertSessionHasNoErrors();
    expect($advance->fresh()->balance())->toBe(500.0);

    ($this->as)($hr)->post("{$this->base}/payroll/runs", ['year' => 2026, 'month' => 12])->assertRedirect();
    $dec = PayrollRun::where('tenant_id', $this->tid)->where('month', 12)->firstOrFail();
    expect((float) Payslip::where('payroll_run_id', $dec->id)->first()->adjustments_deductions)->toBe(500.0); // only what is left

    ($this->as)($hr)->post("{$this->base}/payroll/advances/{$advance->id}/cancel")->assertSessionHasNoErrors();
    ($this->as)($hr)->post("{$this->base}/payroll/runs/{$dec->id}/recalculate");
    expect((float) Payslip::where('payroll_run_id', $dec->id)->first()->adjustments_deductions)->toBe(0.0);

    $csv = ($this->as)($hr)->get("{$this->base}/payroll/runs/{$nov->id}/transfer-list")->assertOk()->streamedContent();
    expect($csv)->toContain('Test Bank')->and($csv)->toContain('EG00TEST')->and($csv)->toContain('net_pay_egp');
});
