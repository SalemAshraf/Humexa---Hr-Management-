<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

/**
 * Opt-in Egypt 2026 starting rules. Every figure is traced to the evidence ledger
 * (Law 91/2005 as replaced by Law 7/2024, Law 148/2019 and its regulations, NOSI 2026
 * limits, Law 16/2018 as amended). It is valid for calendar 2026 only: 2027 insurance
 * limits and the first seven-year rate increase are not announced, so runs for 2027 are
 * refused until the company enters and reviews new rules. It is never applied without a
 * company action, and the company must accept every open item before marking it reviewed.
 */
final class EgyptTemplate
{
    public const KEY = 'egypt-2026';

    /** Known limits. Accepting them records that the company knows what is left out; it verifies nothing. */
    public const ACKNOWLEDGEMENTS = [
        'monthly_tax_method' => 'Monthly income tax is the annual tax on this month projected over 12 months, divided by 12. There is no year-to-date true-up, so a month can differ from what the tax authority method gives.',
        'not_calculated' => 'These are not calculated: the 15% / 10,000 EGP private insurance deduction, the employer Emergency Aid Fund, overtime, end-of-service pay, partial months, leavers and the minimum wage check.',
        'manual_cases' => 'People who joined during the month, insured people aged 60 or over, insured people with no insurable wage or no date of birth, and people marked uninsured without a reason are left out of the run with the reason. Handle them by hand.',
    ];

    /** Shown on every run and in the CSV so omitted items are visible next to the numbers. */
    public const NOT_CALCULATED = [
        'Year-to-date income tax true-up (monthly tax is the annual projection divided by 12)',
        'Private insurance / pension fund tax deduction',
        'Employer Emergency Aid Fund (1% of basic wages, 30+ workers)',
        'Employer insurance rate is entered by the company, not derived',
        'Overtime, end-of-service pay, partial months, leavers, minimum wage check',
        'Insured employees aged 60 or over (left out)',
    ];

    public const LEVY_BASES = ['gross', 'taxable_gross', 'basic'];

    public static function attributes(): array
    {
        return [
            'name' => 'Egypt 2026 estimator template (review required)',
            'country' => 'EG',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'insurance_employee_rate' => 0.11,
            'insurance_employer_rate' => null,
            'insurable_min' => 2700,
            'insurable_max' => 16700,
            'personal_exemption_annual' => 20000,
            'rounding_step' => 10,
            'private_insurance_cap_pct' => 0,
            'private_insurance_cap_amount' => 0,
            'after_tax_levy_rate' => 0.0005,
            'after_tax_levy_label' => "Martyrs' Fund (0.05%)",
            'levy_base' => null,
            'month_days_basis' => 30,
            'strict_inputs' => true,
            'template_key' => self::KEY,
            'required_acknowledgements' => array_keys(self::ACKNOWLEDGEMENTS),
            'accepted_acknowledgements' => [],
            'tax_tables' => [
                ['up_to' => 600000, 'bands' => [[40000, 0], [55000, 0.10], [70000, 0.15], [200000, 0.20], [400000, 0.225], [null, 0.25]]],
                ['up_to' => 700000, 'bands' => [[55000, 0.10], [70000, 0.15], [200000, 0.20], [400000, 0.225], [null, 0.25]]],
                ['up_to' => 800000, 'bands' => [[70000, 0.15], [200000, 0.20], [400000, 0.225], [null, 0.25]]],
                ['up_to' => 900000, 'bands' => [[200000, 0.20], [400000, 0.225], [null, 0.25]]],
                ['up_to' => 1200000, 'bands' => [[400000, 0.225], [null, 0.25]]],
                ['up_to' => null, 'bands' => [[1200000, 0.25], [null, 0.275]]],
            ],
            'sources' => "Income tax: Law 91/2005 Art. 8 as replaced by Law 7/2024 (annual table and high-income phase-out, net income rounded down to 10 EGP), Art. 13 personal exemption 20,000 EGP and employee insurance share deductible before tax. Law 151/2026 does not change Art. 8 or 13.\nInsurance: Law 148/2019 Arts. 19, 36, 46, 70, 86 give employee 9+1+1 = 11% and employer components 12+1+1.5+3.25+1 = 18.75% before establishment-specific adjustments (not pre-filled: the company enters its own employer rate). Limits 2,700 to 16,700 EGP a month from 1 January 2026 (NOSI announcement of 2025-11-30; Executive Regulations Art. 53).\nMartyrs' Fund: Law 16/2018 Art. 8 as amended by Law 4/2021, 0.05% monthly. The base is not defined in the text, so the company must choose it.\nThe rates are valid for 2026 only. Not legal advice and not a compliance certification.",
            'open_questions' => "Not yet covered by authoritative text: 2027 insurance limits and the +1% seven-year increase due 1 January 2027; the statutory article for the private insurance deduction; the official list of included and excluded pay elements; the over-60 rules; the Martyrs' Fund base (company choice, not a legal finding); the Emergency Fund (1% of basic wages, 30+ workers, employer side); the minimum wage of 8,500 EGP from 2027 (announcement only).",
        ];
    }
}
