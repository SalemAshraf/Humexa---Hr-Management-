<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCompensation extends Model
{
    protected $table = 'employee_compensations';

    protected $hidden = ['tenant_id', 'bank_account'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_from' => 'date:Y-m-d', 'basic_salary' => 'float', 'allowances' => 'float', 'insured' => 'boolean', 'insurable_wage' => 'float', 'bank_account' => 'encrypted'];
    }

    /** Last four characters only. The full number never reaches the browser. */
    public function maskedBankAccount(): ?string
    {
        $v = (string) $this->bank_account;

        return $v === '' ? null : '••••'.substr($v, -4);
    }
}
