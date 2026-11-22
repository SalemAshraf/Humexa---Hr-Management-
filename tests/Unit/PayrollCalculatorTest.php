<?php

declare(strict_types=1);

use App\Modules\Payroll\Models\PayrollRuleSet;
use App\Modules\Payroll\Services\PayrollCalculator;

function egyptRules(array $override = []): PayrollRuleSet
{
    return (new PayrollRuleSet)->forceFill(array_merge(payrollTestRules(), $override));
}

test('annual tax follows the six tables including the 600,000 step', function () {
    $c = new PayrollCalculator;
    $t = payrollTestTaxTables();

    expect($c->annualTax($t, 30000))->toBe(0.0)
        ->and($c->annualTax($t, 317950))->toBe(56288.75)
        ->and($c->annualTax($t, 600000))->toBe(124750.0)
        ->and($c->annualTax($t, 600010))->toBe(128752.5)
        ->and($c->annualTax($t, 1200010))->toBe(1200000 * 0.25 + 10 * 0.275 > 0 ? round(0.25 * 1200000 + 0.275 * 10, 2) : 0.0);
});

test('a monthly salary of 30,000 matches the worked example in the sources', function () {
    $r = (new PayrollCalculator)->calculate(egyptRules(), [
        'basic' => 30000.0, 'allowances' => 0.0, 'insured' => true, 'insurable_wage' => null,
        'unpaid_days' => 0.0, 'earnings_taxable' => 0.0, 'earnings_exempt' => 0.0, 'deductions' => 0.0,
    ]);

    expect($r['insurable_wage'])->toBe(16700.0)
        ->and($r['insurance_employee'])->toBe(1837.0)
        ->and($r['insurance_employer'])->toBe(3131.25)
        ->and($r['annual_net_income'])->toBe(317950.0)
        ->and($r['income_tax'])->toBe(4690.73)
        ->and($r['net_pay'])->toBe(23472.27);
});

test('insurable wage is clamped, unpaid leave reduces pay, uninsured pays no insurance', function () {
    $calc = new PayrollCalculator;
    $base = ['allowances' => 0.0, 'unpaid_days' => 0.0, 'earnings_taxable' => 0.0, 'earnings_exempt' => 0.0, 'deductions' => 0.0, 'insurable_wage' => null];

    $low = $calc->calculate(egyptRules(), $base + ['basic' => 1000.0, 'insured' => true]);
    expect($low['insurable_wage'])->toBe(2700.0)->and($low['income_tax'])->toBe(0.0);

    $unpaid = $calc->calculate(egyptRules(), ['basic' => 6000.0, 'insured' => false, 'unpaid_days' => 3.0] + $base);
    expect($unpaid['unpaid_leave_deduction'])->toBe(600.0)->and($unpaid['gross'])->toBe(5400.0)->and($unpaid['insurance_employee'])->toBe(0.0);

    $levied = $calc->calculate(egyptRules(['after_tax_levy_rate' => 0.0005]), ['basic' => 10000.0, 'insured' => false] + $base);
    expect($levied['after_tax_levy'])->toBe(5.0);
});

test('net income is rounded down to the nearest 10 before the table is chosen', function () {
    $calc = new PayrollCalculator;
    $base = ['allowances' => 0.0, 'unpaid_days' => 0.0, 'earnings_taxable' => 0.0, 'earnings_exempt' => 0.0, 'deductions' => 0.0, 'insurable_wage' => null, 'insured' => false];
    // 12 x 3,334.58 = 40,014.96 less 20,000 exemption = 20,014.96 -> 20,010
    $r = $calc->calculate(egyptRules(), ['basic' => 3334.58] + $base);
    expect($r['annual_net_income'])->toBe(20010.0);
    // 12 x 5,000 = 60,000 less 20,000 = 40,000 -> still the 0% band
    expect($calc->calculate(egyptRules(), ['basic' => 5000.0] + $base)['income_tax'])->toBe(0.0);
    // 12 x 5,010 = 60,120 less 20,000 = 40,120 -> 10% of 120 = 12 a year = 1.00 a month
    expect($calc->calculate(egyptRules(), ['basic' => 5010.0] + $base)['income_tax'])->toBe(1.0);
});
