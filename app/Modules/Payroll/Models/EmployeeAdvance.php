<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAdvance extends Model
{
    protected $table = 'employee_advances';

    protected $hidden = ['tenant_id'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['principal' => 'float', 'installment' => 'float'];
    }

    /** Taken by approved runs only. A draft run never reduces the balance. */
    public function repaid(?int $exceptRunId = null): float
    {
        return (float) AdvanceInstallment::query()->where('employee_advance_id', $this->id)
            ->when($exceptRunId, fn ($q) => $q->where('payroll_run_id', '!=', $exceptRunId))->sum('amount');
    }

    public function balance(?int $exceptRunId = null): float
    {
        return round(max(0, $this->principal - $this->repaid($exceptRunId)), 2);
    }

    public function startsOnOrBefore(int $year, int $month): bool
    {
        return ($this->start_year * 12 + $this->start_month) <= ($year * 12 + $month);
    }
}
