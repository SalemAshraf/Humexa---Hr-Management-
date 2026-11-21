<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

/**
 * Starting values for an Egypt rule set. They are a draft for the company to check,
 * never confirmed automatically. Every figure carries where it was read.
 */
final class EgyptRuleDefaults
{
    public static function attributes(): array
    {
        return [
            'name' => 'Egypt 2026 ESTIMATOR (unverified starting values)',
            'country' => 'EG',
            'effective_from' => '2026-01-01',
            'insurance_employee_rate' => 0.11,
            'insurance_employer_rate' => 0.1875,
            'insurable_min' => 2700,
            'insurable_max' => 16700,
            'personal_exemption_annual' => 20000,
            'rounding_step' => 10,
            'private_insurance_cap_pct' => 0.15,
            'private_insurance_cap_amount' => 10000,
            'after_tax_levy_rate' => 0,
            'after_tax_levy_label' => null,
            'month_days_basis' => 30,
            'tax_tables' => self::taxTables(),
            'sources' => implode("\n", [
                'Insurable wage limits for 2026 (minimum 2,700, maximum 16,700): National Organization for Social Insurance news, 30 Nov 2025: https://www.nosi.gov.eg/ar/News/Pages/2025-11-30.aspx',
                'Social insurance shares for a private-sector employee (employee 11%, employer 18.75%): Law 148 of 2019 as summarised by secondary sources (npiegypt.com, keyt.cloud). Not read from the law text.',
                'Annual salary tax bands, first table (net income up to 600,000): Law 7 of 2024, Article 8, official gazette text: https://portal.eta.gov.eg/sites/default/files/2024-03/law_no.7-2024.pdf',
                'Higher tables (net income above 600,000): described by secondary sources (workegypt.africa, npiegypt.com). The official table layout could not be parsed here.',
                'Personal exemption 20,000 a year and the private insurance cap (15% of net income or 10,000): Egyptian Tax Authority page https://eta.gov.eg/ar/node/1382',
            ]),
            'open_questions' => implode("\n", [
                'Law 151 of 2026 (official text read: https://eta.gov.eg/sites/default/files/2026-08/law.no_.151.of_.2026.pdf) lists the articles it amends, adds and repeals. Article 8 (rates) and Article 13 (exemption) are not among them. The salary chapter article numbers were not mapped independently, so this is not a full clearance.',
                'Martyrs fund (reported as 0.05% of gross) and comprehensive health insurance (reported as 1% where it applies) are left at 0, so net pay is probably overstated. Law 151 of 2026 also mentions a health insurance solidarity contribution (for business income). No official source for the salary levies was read.',
                'The tax method is simplified: each month is the annual tax on the month figure times 12, divided by 12. The tax authority method uses a running total with true-ups.',
                'The higher tax tables (net income above 600,000) and the 11%/18.75% insurance rates were not read from an official table or law text here.',
                'Insurable wage is whatever is registered per employee, kept between the limits. Rules on which pay elements count are not modelled.',
                'Income tax on end of service pay, two-job withholding and stamp duty are not modelled.',
            ]),
        ];
    }

    /** Each table applies to a whole net income up to its limit. Bands are [upper limit or null, rate]. */
    public static function taxTables(): array
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
}
