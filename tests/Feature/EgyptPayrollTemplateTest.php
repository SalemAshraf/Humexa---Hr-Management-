<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Payroll\Models\PayrollRuleSet;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\EgyptTemplate;
use App\Modules\Payroll\Services\PayrollCalculator;
use App\Modules\Tenants\Actions\RegisterCompanyAction;
use App\Modules\Users\Support\TenantRoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config(['humexa.tenant_domain' => 'humexa.test', 'humexa.reserved_subdomains' => [], 'tenancy.central_domains' => ['humexa.test']]);
    $this->reg = app(RegisterCompanyAction::class)->execute(companyName: 'Egy Co', adminName: 'Egy Owner', email: 'egy-owner@tenant-test.example', password: 'StrongPassword123!');
    $this->tid = (string) $this->reg['tenant']->id;
    $this->base = 'http://'.$this->reg['domain'];
    $this->hr = $this->reg['tenant']->run(function () {
        $u = new User(['name' => 'hr', 'email' => 'hr-egy@tenant-test.example', 'password' => 'StrongPassword123!']);
        $u->forceFill(['tenant_id' => $this->tid, 'account_type' => User::ACCOUNT_TYPE_TENANT])->save();
        $u->assignRole(TenantRoleCatalog::HR_ADMIN);

        return $u;
    });
    $this->as = fn () => $this->actingAs($this->hr, 'tenant')->withSession(['tenant_session_version' => 1]);
    $this->emp = fn (string $no, array $o = []) => Employee::query()->forceCreate($o + [
        'tenant_id' => $this->tid, 'employee_number' => $no, 'first_name' => $no, 'last_name' => 'Test',
        'employment_status' => 'active', 'hire_date' => '2024-01-01', 'date_of_birth' => '1990-05-05',
    ]);
});

function egyptTemplateRules(): PayrollRuleSet
{
    return (new PayrollRuleSet)->forceFill(['insurance_employer_rate' => 0.1875, 'levy_base' => 'gross'] + EgyptTemplate::attributes());
}

test('the template tax tables match the Law 7/2024 table at every step', function () {
    $calc = new PayrollCalculator;
    $t = EgyptTemplate::attributes()['tax_tables'];
    // Net income, expected annual tax (worked by hand from the ledger table).
    foreach ([40000 => 0, 55000 => 1500, 70000 => 3750, 200000 => 29750, 400000 => 74750, 600000 => 124750,
        700000 => 153750, 800000 => 181500, 900000 => 210000, 1200000 => 290000, 1300000 => 327500] as $net => $tax) {
        expect($calc->annualTax($t, (float) $net))->toEqual((float) $tax, "net {$net}");
    }
});

test('a 30,000 salary under the template gives the hand-worked figures', function () {
    $r = (new PayrollCalculator)->calculate(egyptTemplateRules(), ['basic' => 30000.0, 'allowances' => 0.0, 'insured' => true, 'insurable_wage' => 16700.0,
        'unpaid_days' => 0.0, 'earnings_taxable' => 0.0, 'earnings_exempt' => 0.0, 'deductions' => 0.0]);
    // insurance 11% of 16,700 = 1,837; annual net = 360,000 - 22,044 - 20,000 = 317,956 -> 317,950
    // tax = 1,500 + 2,250 + 26,000 + 117,950 x 22.5% = 56,288.75 a year -> 4,690.73 a month
    expect($r['insurance_employee'])->toBe(1837.0)->and($r['insurance_employer'])->toBe(3131.25)->and($r['annual_net_income'])->toBe(317950.0)
        ->and($r['income_tax'])->toBe(4690.73)->and($r['after_tax_levy'])->toBe(15.0)->and($r['net_pay'])->toBe(23457.27);
});

test('loading the template is opt-in, never overwrites, and needs every open item accepted', function () {
    ($this->as)()->get("{$this->base}/payroll")->assertOk();
    expect(PayrollRuleSet::where('tenant_id', $this->tid)->count())->toBe(0);

    ($this->as)()->post("{$this->base}/payroll/rules/egypt-template")->assertSessionHasNoErrors();
    ($this->as)()->post("{$this->base}/payroll/rules/egypt-template")->assertSessionHasErrors('rules');
    $rules = PayrollRuleSet::where('tenant_id', $this->tid)->firstOrFail();
    expect(PayrollRuleSet::where('tenant_id', $this->tid)->count())->toBe(1)->and($rules->isConfirmed())->toBeFalse()
        ->and($rules->effective_to->toDateString())->toBe('2026-12-31');

    // Employer rate and levy base are company inputs: the template does not fill them, so the set is incomplete.
    expect($rules->missingSettings())->toContain('insurance_employer_rate')->toContain('levy_base');
    ($this->as)()->post("{$this->base}/payroll/rules/confirm", ['acknowledged' => array_keys(EgyptTemplate::ACKNOWLEDGEMENTS)])->assertSessionHasErrors('rules');
    expect($rules->fresh()->isConfirmed())->toBeFalse();

    $a = EgyptTemplate::attributes();
    $put = fn (array $o = []) => ($this->as)()->put("{$this->base}/payroll/rules", $o + ['name' => $a['name'], 'effective_from' => '2026-01-01', 'insurance_employee_rate' => 0.11, 'insurance_employer_rate' => 0.1875,
        'insurable_min' => 2700, 'insurable_max' => 16700, 'personal_exemption_annual' => 20000, 'rounding_step' => 10, 'after_tax_levy_rate' => 0.0005, 'levy_base' => 'gross', 'month_days_basis' => 30,
        'tax_tables_json' => json_encode($a['tax_tables'])]);
    $put(['levy_base' => ''])->assertSessionHasErrors('levy_base');
    $put(['levy_base' => 'nonsense'])->assertSessionHasErrors('levy_base');
    $put()->assertSessionHasNoErrors();

    // Known limits must all be accepted, and accepting them is recorded but does not stand in for inputs.
    ($this->as)()->post("{$this->base}/payroll/rules/confirm")->assertSessionHasErrors('rules');
    ($this->as)()->post("{$this->base}/payroll/rules/confirm", ['acknowledged' => ['manual_cases']])->assertSessionHasErrors('rules');
    ($this->as)()->post("{$this->base}/payroll/runs", ['year' => 2026, 'month' => 11])->assertSessionHasErrors('run');
    ($this->as)()->post("{$this->base}/payroll/rules/confirm", ['acknowledged' => array_keys(EgyptTemplate::ACKNOWLEDGEMENTS)])->assertSessionHasNoErrors();
    expect($rules->fresh()->isConfirmed())->toBeTrue()->and($rules->fresh()->levy_base)->toBe('gross');

    // Editing a value clears the review and the accepted items, and keeps the end date.
    $put(['insurable_max' => 17000])->assertSessionHasNoErrors();
    expect($rules->fresh()->isConfirmed())->toBeFalse()->and($rules->fresh()->effective_to->toDateString())->toBe('2026-12-31');
});

test('the levy follows the base the company chose', function () {
    $in = ['basic' => 20000.0, 'allowances' => 10000.0, 'insured' => false, 'insurable_wage' => null, 'unpaid_days' => 0.0, 'earnings_taxable' => 0.0, 'earnings_exempt' => 5000.0, 'deductions' => 0.0];
    foreach (['gross' => 35000 * 0.0005, 'taxable_gross' => 30000 * 0.0005, 'basic' => 20000 * 0.0005] as $base => $levy) {
        $r = egyptTemplateRules()->forceFill(['levy_base' => $base]);
        expect((new PayrollCalculator)->calculate($r, $in)['after_tax_levy'])->toBe(round($levy, 2), $base);
    }
});

test('2027 is refused, and unresolved inputs leave the person out instead of guessing', function () {
    ($this->as)()->post("{$this->base}/payroll/rules/egypt-template");
    PayrollRuleSet::where('tenant_id', $this->tid)->update(['insurance_employer_rate' => 0.1875, 'levy_base' => 'gross']);
    ($this->as)()->post("{$this->base}/payroll/rules/confirm", ['acknowledged' => array_keys(EgyptTemplate::ACKNOWLEDGEMENTS)])->assertSessionHasNoErrors();

    $ok = ($this->emp)('OK');
    $noWage = ($this->emp)('NOWAGE');
    $noDob = ($this->emp)('NODOB', ['date_of_birth' => null]);
    $senior = ($this->emp)('SENIOR', ['date_of_birth' => '1960-01-01']);
    $joiner = ($this->emp)('JOINER', ['hire_date' => '2026-11-10']);
    $uninsured = ($this->emp)('UNINS', ['date_of_birth' => null]);
    $unreasoned = ($this->emp)('NOREASON');
    foreach ([[$ok, 16700, true], [$noWage, null, true], [$noDob, 16700, true], [$senior, 16700, true], [$joiner, 16700, true], [$uninsured, null, false], [$unreasoned, null, false]] as [$e, $wage, $insured]) {
        ($this->as)()->post("{$this->base}/payroll/employees/{$e->id}/compensation", ['effective_from' => '2026-01-01', 'basic_salary' => 30000, 'allowances' => 0, 'insured' => $insured, 'insurable_wage' => $wage, 'note' => $e->id === $uninsured->id ? 'Foreign national on payroll abroad' : null])->assertSessionHasNoErrors();
    }

    // No silent extension into 2027, and none into years before the sources.
    ($this->as)()->post("{$this->base}/payroll/runs", ['year' => 2027, 'month' => 1])->assertSessionHasErrors('run');
    ($this->as)()->post("{$this->base}/payroll/runs", ['year' => 2025, 'month' => 12])->assertSessionHasErrors('run');
    expect(\App\Modules\Payroll\Models\PayrollRun::where('tenant_id', $this->tid)->count())->toBe(0);

    ($this->as)()->post("{$this->base}/payroll/runs", ['year' => 2026, 'month' => 11])->assertRedirect();
    $paid = Payslip::where('tenant_id', $this->tid)->pluck('employee_id')->all();
    expect($paid)->toContain($ok->id)->toContain($uninsured->id)
        ->not->toContain($noWage->id)->not->toContain($noDob->id)->not->toContain($senior->id)->not->toContain($joiner->id)->not->toContain($unreasoned->id);

    $slip = Payslip::where('employee_id', $ok->id)->firstOrFail();
    expect((float) $slip->net_pay)->toBe(23457.27)->and($slip->details['rules_snapshot']['effective_to'])->not->toBeNull()
        ->and($slip->details['provisional']['levy_base_chosen_by_company'])->toBe('gross')->and($slip->details['provisional']['not_calculated'])->not->toBeEmpty();
    $run = \App\Modules\Payroll\Models\PayrollRun::where('tenant_id', $this->tid)->firstOrFail();
    ($this->as)()->get("{$this->base}/payroll/runs/{$run->id}")->assertInertia(fn ($p) => $p->where('provisional.valid_to', '2026-12-31')->has('provisional.not_calculated'));
    expect(($this->as)()->get("{$this->base}/payroll/runs/{$run->id}/export")->streamedContent())->toContain('Provisional estimate');

    // December 2026 is still covered; recalculating a draft after the end date is also refused.
    ($this->as)()->post("{$this->base}/payroll/runs", ['year' => 2026, 'month' => 12])->assertRedirect();
});
