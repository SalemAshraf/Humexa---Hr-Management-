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
            'confirmed_at' => 'datetime',
        ];
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
