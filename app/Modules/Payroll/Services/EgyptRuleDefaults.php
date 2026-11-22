<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

/**
 * A blank rule set. Afrad ships no statutory rates: every rate, limit and tax
 * table is entered by the company, and a run cannot be created until all of
 * them are set and the company has marked the set as reviewed.
 */
final class EgyptRuleDefaults
{
    public static function attributes(): array
    {
        return [
            'name' => 'Company payroll rules',
            'country' => 'EG',
            'effective_from' => now()->startOfYear()->toDateString(),
            'insurance_employee_rate' => null,
            'insurance_employer_rate' => null,
            'insurable_min' => null,
            'insurable_max' => null,
            'personal_exemption_annual' => null,
            'rounding_step' => 10,
            'private_insurance_cap_pct' => 0,
            'private_insurance_cap_amount' => 0,
            'after_tax_levy_rate' => 0,
            'after_tax_levy_label' => null,
            'month_days_basis' => 30,
            'tax_tables' => [],
            'sources' => null,
            'open_questions' => null,
        ];
    }
}
