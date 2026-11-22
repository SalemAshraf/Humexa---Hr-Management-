<?php

pest()->extend(Tests\TestCase::class)
    ->in('Feature');


/** Arbitrary round numbers for tests only. They are not statutory references. */
function payrollTestTaxTables(): array
{
        return [
            ['up_to' => 600000, 'bands' => [[40000, 0], [55000, 0.10], [70000, 0.15], [200000, 0.20], [400000, 0.225], [null, 0.25]]],
            ['up_to' => 700000, 'bands' => [[55000, 0.10], [70000, 0.15], [200000, 0.20], [400000, 0.225], [null, 0.25]]],
            ['up_to' => 800000, 'bands' => [[70000, 0.15], [200000, 0.20], [400000, 0.225], [null, 0.25]]],
            ['up_to' => 900000, 'bands' => [[200000, 0.20], [400000, 0.225], [null, 0.25]]],
            ['up_to' => 1200000, 'bands' => [[400000, 0.225], [null, 0.25]]],
            ['up_to' => null, 'bands' => [[1200000, 0.25], [null, 0.275]]],
        ];
}

function payrollTestRules(): array
{
    return [
        'name' => 'Test rules', 'country' => 'EG', 'effective_from' => '2026-01-01',
        'insurance_employee_rate' => 0.11, 'insurance_employer_rate' => 0.1875,
        'insurable_min' => 2700, 'insurable_max' => 16700, 'personal_exemption_annual' => 20000,
        'rounding_step' => 10, 'private_insurance_cap_pct' => 0.15, 'private_insurance_cap_amount' => 10000,
        'after_tax_levy_rate' => 0, 'after_tax_levy_label' => null, 'month_days_basis' => 30,
        'tax_tables' => payrollTestTaxTables(), 'sources' => null, 'open_questions' => null,
    ];
}
