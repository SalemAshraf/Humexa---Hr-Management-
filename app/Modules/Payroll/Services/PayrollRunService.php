<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\Employees\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Services\LeaveService;
use App\Modules\Payroll\Models\EmployeeCompensation;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollRuleSet;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollRunService
{
    public const PAYABLE_STATUSES = [Employee::STATUS_PROBATION, Employee::STATUS_ACTIVE, Employee::STATUS_ON_LEAVE];

    public function __construct(private readonly PayrollCalculator $calculator, private readonly LeaveService $leave)
    {
    }

    /**
     * (Re)build every payslip of a draft run from current records.
     *
     * @return list<string> employees that were left out, with the reason
     */
    public function build(PayrollRun $run): array
    {
        abort_unless($run->status === PayrollRun::DRAFT, 422, 'An approved run cannot be recalculated.');

        $tenantId = (string) $run->tenant_id;
        $rules = PayrollRuleSet::query()->where('tenant_id', $tenantId)->findOrFail($run->rule_set_id);
        abort_if($rules->missingSettings() !== [], 422, 'The payroll rules are incomplete.');
        abort_unless($rules->coversMonth((int) $run->year, (int) $run->month), 422, 'These payroll rules end before this month. Enter and review rules for this period first.');
        $start = Carbon::create($run->year, $run->month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $weekend = $this->leave->weekendDays($tenantId);
        $skipped = [];

        DB::transaction(function () use ($run, $tenantId, $rules, $start, $end, $weekend, &$skipped) {
            Payslip::query()->where('payroll_run_id', $run->id)->delete();

            $employees = Employee::query()->where('tenant_id', $tenantId)
                ->where(fn ($q) => $q->whereNull('hire_date')->orWhereDate('hire_date', '<=', $end->toDateString()))
                ->orderBy('id')->get();

            foreach ($employees as $employee) {
                if (! in_array($employee->employment_status, self::PAYABLE_STATUSES, true)) {
                    continue;
                }

                $comp = EmployeeCompensation::query()->where('tenant_id', $tenantId)->where('employee_id', $employee->id)
                    ->whereDate('effective_from', '<=', $end->toDateString())->orderByDesc('effective_from')->first();

                if ($comp === null) {
                    $skipped[] = $employee->full_name.': no salary recorded yet';

                    continue;
                }

                if ($rules->strict_inputs) {
                    if ($employee->hire_date !== null && $employee->hire_date->gt($start)) {
                        $skipped[] = $employee->full_name.': joined during this month, pay and insurance for a partial first month are not calculated, handle by hand';

                        continue;
                    }

                    if (! $comp->insured && trim((string) $comp->note) === '') {
                        $skipped[] = $employee->full_name.': marked uninsured with no reason in the salary note, record why or mark as insured';

                        continue;
                    }

                    if ($comp->insured && $comp->insurable_wage === null) {
                        $skipped[] = $employee->full_name.': insured but no insurable wage entered, enter it on the salary record';

                        continue;
                    }

                    if ($comp->insured && $employee->date_of_birth === null) {
                        $skipped[] = $employee->full_name.': insured but no date of birth, needed to check the age 60 insurance rules';

                        continue;
                    }

                    if ($comp->insured && $employee->date_of_birth->copy()->addYears(60)->lte($end)) {
                        $skipped[] = $employee->full_name.': aged 60 or over, different insurance rules apply, handle by hand';

                        continue;
                    }
                }

                $adjustments = PayrollAdjustment::query()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->get();
                $earnTaxable = (float) $adjustments->where('kind', 'earning')->where('taxable', true)->sum('amount');
                $earnExempt = (float) $adjustments->where('kind', 'earning')->where('taxable', false)->sum('amount');
                $deductions = (float) $adjustments->where('kind', 'deduction')->sum('amount');

                $unpaidDays = $this->unpaidDays($tenantId, (int) $employee->id, $start, $end, $weekend);

                $result = $this->calculator->calculate($rules, [
                    'basic' => $comp->basic_salary, 'allowances' => $comp->allowances, 'insured' => $comp->insured,
                    'insurable_wage' => $comp->insurable_wage, 'unpaid_days' => $unpaidDays,
                    'earnings_taxable' => $earnTaxable, 'earnings_exempt' => $earnExempt, 'deductions' => $deductions,
                ]);

                Payslip::query()->create([
                    'tenant_id' => $tenantId, 'payroll_run_id' => $run->id, 'employee_id' => $employee->id,
                    'basic_salary' => $comp->basic_salary, 'allowances' => $comp->allowances,
                    'unpaid_leave_days' => $unpaidDays, 'unpaid_leave_deduction' => $result['unpaid_leave_deduction'],
                    'adjustments_earnings' => $earnTaxable + $earnExempt, 'adjustments_deductions' => $deductions,
                    'gross' => $result['gross'], 'insurable_wage' => $result['insurable_wage'],
                    'insurance_employee' => $result['insurance_employee'], 'insurance_employer' => $result['insurance_employer'],
                    'annual_net_income' => $result['annual_net_income'], 'income_tax' => $result['income_tax'],
                    'after_tax_levy' => $result['after_tax_levy'], 'net_pay' => $result['net_pay'],
                    'details' => ['compensation_effective_from' => $comp->effective_from->toDateString(), 'insured' => $comp->insured, 'estimate_only' => true,
                        'rules_snapshot' => $rules->only(['id', 'levy_base', 'name', 'effective_from', 'effective_to', 'template_key', 'strict_inputs', 'insurance_employee_rate', 'insurance_employer_rate', 'insurable_min', 'insurable_max', 'personal_exemption_annual', 'rounding_step', 'private_insurance_cap_pct', 'private_insurance_cap_amount', 'after_tax_levy_rate', 'after_tax_levy_label', 'month_days_basis', 'tax_tables']), 'provisional' => $rules->strict_inputs ? [
                        'kind' => 'reviewed-assumptions estimate, not a statutory result',
                        'valid_from' => $rules->effective_from->toDateString(), 'valid_to' => $rules->effective_to?->toDateString(),
                        'levy_base_chosen_by_company' => $rules->levy_base, 'insurance_declared_by_company' => $comp->insured ? 'insured' : 'uninsured: '.$comp->note,
                        'not_calculated' => EgyptTemplate::NOT_CALCULATED,
                    ] : null, 'rules_confirmed_at' => $rules->confirmed_at?->toDateTimeString()],
                ]);
            }
        });

        return $skipped;
    }

    /** Working days of approved unpaid leave that fall inside the month. */
    private function unpaidDays(string $tenantId, int $employeeId, Carbon $start, Carbon $end, array $weekend): float
    {
        $requests = LeaveRequest::query()->where('tenant_id', $tenantId)->where('employee_id', $employeeId)
            ->where('status', LeaveRequest::APPROVED)
            ->whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())
            ->whereHas('type', fn ($q) => $q->where('tenant_id', $tenantId)->where('is_paid', false))
            ->get();

        $days = 0.0;

        foreach ($requests as $r) {
            $from = $r->start_date->copy()->max($start);
            $to = $r->end_date->copy()->min($end);
            $days += $this->leave->workingDays($from, $to, $weekend);
        }

        return $days;
    }
}
