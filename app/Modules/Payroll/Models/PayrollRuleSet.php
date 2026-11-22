<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRuleSet extends Model
{
    protected $hidden = ['tenant_id'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date:Y-m-d',
            'tax_tables' => 'array',
            'insurance_employee_rate' => 'float', 'insurance_employer_rate' => 'float',
            'insurable_min' => 'float', 'insurable_max' => 'float', 'personal_exemption_annual' => 'float',
            'private_insurance_cap_pct' => 'float', 'private_insurance_cap_amount' => 'float', 'after_tax_levy_rate' => 'float',
            'confirmed_at' => 'datetime', 'effective_to' => 'date:Y-m-d', 'strict_inputs' => 'boolean',
            'required_acknowledgements' => 'array', 'accepted_acknowledgements' => 'array',
        ];
    }

    public const REQUIRED = ['insurance_employee_rate', 'insurance_employer_rate', 'insurable_min', 'insurable_max', 'personal_exemption_annual', 'tax_tables'];

    /** @return list<string> required settings the company has not entered yet */
    public function missingSettings(): array
    {
        return array_values(array_filter(self::REQUIRED, fn (string $f) => $this->getAttribute($f) === null || $this->getAttribute($f) === []));
    }

    /** @return list<string> open items the company has not accepted yet */
    public function pendingAcknowledgements(): array
    {
        return array_values(array_diff($this->required_acknowledgements ?? [], $this->accepted_acknowledgements ?? []));
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null && $this->pendingAcknowledgements() === [];
    }

    /** A rule set is never used after its end date, so a period its sources do not cover is refused. */
    public function coversMonth(int $year, int $month): bool
    {
        $end = \Illuminate\Support\Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();

        return $this->effective_to === null || $this->effective_to->copy()->startOfDay()->gte($end);
    }
}
