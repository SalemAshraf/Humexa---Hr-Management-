<?php

declare(strict_types=1);

use App\Modules\Payroll\Models\PayrollRuleSet;
use App\Modules\Payroll\Services\EgyptRuleDefaults;
use App\Modules\Payroll\Services\PayrollCalculator;

function egyptRules(array $override = []): PayrollRuleSet
{
    return (new PayrollRuleSet)->forceFill(array_merge(EgyptRuleDefaults::attributes(), $override));
}

test('annual tax follows the six tables including the 600,000 step', function () {
    $c = new PayrollCalculator;
    $t = EgyptRuleDefaults::taxTables();

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
