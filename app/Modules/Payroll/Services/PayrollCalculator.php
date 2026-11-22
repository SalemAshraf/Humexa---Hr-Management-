<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Models\PayrollRuleSet;

/**
 * Pure calculation for one employee and one month under one rule set.
 * Amounts are computed in whole piastres to avoid float drift.
 */
class PayrollCalculator
{
    /**
     * @param  array{basic:float,allowances:float,insured:bool,insurable_wage:?float,unpaid_days:float,earnings_taxable:float,earnings_exempt:float,deductions:float}  $in
     */
    public function calculate(PayrollRuleSet $rules, array $in): array
    {
        $basis = max(1, (int) $rules->month_days_basis);
        $monthlyPay = $in['basic'] + $in['allowances'];
        $unpaid = $this->round2($monthlyPay / $basis * $in['unpaid_days']);

        $gross = $this->round2($monthlyPay - $unpaid + $in['earnings_taxable'] + $in['earnings_exempt']);
        $taxableGross = $this->round2($monthlyPay - $unpaid + $in['earnings_taxable']);

        $insurable = 0.0;
        if ($in['insured']) {
            $base = $in['insurable_wage'] ?? $in['basic'];
            $insurable = min(max($base, $rules->insurable_min), $rules->insurable_max);
        }

        $insEmployee = $this->round2($insurable * $rules->insurance_employee_rate);
        $insEmployer = $this->round2($insurable * $rules->insurance_employer_rate);

        $annualTaxable = $taxableGross * 12;
        $annualNet = max(0.0, $annualTaxable - $insEmployee * 12 - $rules->personal_exemption_annual);
        $step = max(1, (int) $rules->rounding_step);
        $annualNet = floor($annualNet / $step) * $step;

        $annualTax = $this->annualTax($rules->tax_tables, $annualNet);
        $monthlyTax = $this->round2($annualTax / 12);
        $levyBase = match ($rules->levy_base) {
            'basic' => (float) $in['basic'],
            'taxable_gross' => $taxableGross,
            default => $gross,
        };
        $levy = $this->round2($levyBase * $rules->after_tax_levy_rate);

        $net = $this->round2($gross - $insEmployee - $monthlyTax - $levy - $in['deductions']);

        return [
            'unpaid_leave_deduction' => $unpaid,
            'gross' => $gross,
            'insurable_wage' => $this->round2($insurable),
            'insurance_employee' => $insEmployee,
            'insurance_employer' => $insEmployer,
            'annual_net_income' => $this->round2($annualNet),
            'income_tax' => $monthlyTax,
            'after_tax_levy' => $levy,
            'net_pay' => $net,
        ];
    }

    /** @param list<array{up_to:?int|float,bands:list<array{0:?int|float,1:int|float}>}> $tables */
    public function annualTax(array $tables, float $annualNet): float
    {
        $table = null;

        foreach ($tables as $candidate) {
            if ($candidate['up_to'] === null || $annualNet <= $candidate['up_to']) {
                $table = $candidate;
                break;
            }
        }

        if ($table === null) {
            return 0.0;
        }

        $tax = 0.0;
        $lower = 0.0;

        foreach ($table['bands'] as [$upper, $rate]) {
            if ($annualNet <= $lower) {
                break;
            }

            $slice = ($upper === null ? $annualNet : min($annualNet, (float) $upper)) - $lower;
            $tax += $slice * (float) $rate;
            $lower = $upper === null ? $annualNet : (float) $upper;
        }

        return $this->round2($tax);
    }

    private function round2(float $value): float
    {
        return round($value + 1e-9, 2);
    }
}
