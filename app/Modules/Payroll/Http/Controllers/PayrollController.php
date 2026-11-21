<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithTenant;
use App\Http\Controllers\Controller;
use App\Modules\Employees\Models\Employee;
use App\Modules\Payroll\Models\EmployeeCompensation;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollRuleSet;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\EgyptRuleDefaults;
use App\Modules\Payroll\Services\PayrollRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    use InteractsWithTenant;

    private const PAYSLIP_FIELDS = ['basic_salary', 'allowances', 'unpaid_leave_days', 'unpaid_leave_deduction', 'adjustments_earnings', 'adjustments_deductions', 'gross', 'insurable_wage', 'insurance_employee', 'insurance_employer', 'annual_net_income', 'income_tax', 'after_tax_levy', 'net_pay'];

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $rules = $this->activeRules($tenantId, create: $request->user('tenant')?->can('payroll.manage') ?? false);

        $employees = Employee::query()->where('tenant_id', $tenantId)
            ->whereNotIn('employment_status', [Employee::STATUS_DRAFT, Employee::STATUS_RESIGNED, Employee::STATUS_TERMINATED])
            ->orderBy('first_name')->get(['id', 'tenant_id', 'employee_number', 'first_name', 'middle_name', 'last_name']);

        $latest = EmployeeCompensation::query()->where('tenant_id', $tenantId)->orderBy('effective_from')->get()->groupBy('employee_id')->map->last();

        return Inertia::render('Company/Payroll/Index', [
            'rules' => $rules ? $this->rulesPayload($rules) : null,
            'runs' => PayrollRun::query()->where('tenant_id', $tenantId)->orderByDesc('year')->orderByDesc('month')->get()->map(fn (PayrollRun $r) => [
                'id' => $r->id, 'year' => $r->year, 'month' => $r->month, 'status' => $r->status,
                'employees' => Payslip::where('payroll_run_id', $r->id)->count(),
                'net_total' => round((float) Payslip::where('payroll_run_id', $r->id)->sum('net_pay'), 2),
            ])->values(),
            'employees' => $employees->map(fn (Employee $e) => [
                'id' => $e->id, 'name' => $e->full_name, 'employee_number' => $e->employee_number,
                'compensation' => ($c = $latest->get($e->id)) ? [
                    'effective_from' => $c->effective_from->toDateString(), 'basic_salary' => $c->basic_salary, 'allowances' => $c->allowances,
                    'insured' => $c->insured, 'insurable_wage' => $c->insurable_wage, 'note' => $c->note,
                ] : null,
            ])->values(),
            'abilities' => ['manage' => (bool) $request->user('tenant')?->can('payroll.manage')],
        ]);
    }

    public function updateRules(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $rules = $this->activeRules($tenantId, create: true);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'effective_from' => ['required', 'date'],
            'insurance_employee_rate' => ['required', 'numeric', 'between:0,1'],
            'insurance_employer_rate' => ['required', 'numeric', 'between:0,1'],
            'insurable_min' => ['required', 'numeric', 'min:0'],
            'insurable_max' => ['required', 'numeric', 'gte:insurable_min'],
            'personal_exemption_annual' => ['required', 'numeric', 'min:0'],
            'rounding_step' => ['required', 'integer', 'min:1', 'max:1000'],
            'after_tax_levy_rate' => ['required', 'numeric', 'between:0,0.5'],
            'after_tax_levy_label' => ['nullable', 'string', 'max:80'],
            'month_days_basis' => ['required', 'integer', 'between:20,31'],
            'tax_tables_json' => ['required', 'json'],
        ]);

        $tables = json_decode($data['tax_tables_json'], true);
        $this->validateTables($tables);
        unset($data['tax_tables_json']);

        $rules->fill($data + ['tax_tables' => $tables, 'confirmed_by_user_id' => null, 'confirmed_at' => null])->save();

        return back()->with('status', 'Rules saved. Confirm them again before creating a run.');
    }

    public function confirmRules(Request $request): RedirectResponse
    {
        $rules = $this->activeRules($this->tenantId(), create: false);
        abort_if($rules === null, 404);

        $rules->forceFill(['confirmed_by_user_id' => $request->user('tenant')->id, 'confirmed_at' => now()])->save();

        return back()->with('status', 'Rules confirmed.');
    }

    public function storeCompensation(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $employee = Employee::query()->where('tenant_id', $tenantId)->findOrFail($request->route('employee'));

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'allowances' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'insured' => ['required', 'boolean'],
            'insurable_wage' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:190'],
        ]);

        EmployeeCompensation::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'employee_id' => $employee->id, 'effective_from' => $data['effective_from']],
            $data + ['allowances' => $data['allowances'] ?? 0, 'created_by_user_id' => $request->user('tenant')->id],
        );

        return back()->with('status', 'Salary saved.');
    }

    public function storeRun(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $rules = $this->activeRules($tenantId, create: false);

        if ($rules === null || ! $rules->isConfirmed()) {
            return back()->withErrors(['run' => 'Confirm the payroll rules before creating a run.']);
        }

        $data = $request->validate(['year' => ['required', 'integer', 'between:2020,2100'], 'month' => ['required', 'integer', 'between:1,12']]);

        if (PayrollRun::where('tenant_id', $tenantId)->where('year', $data['year'])->where('month', $data['month'])->exists()) {
            return back()->withErrors(['run' => 'A run for that month already exists.']);
        }

        $run = PayrollRun::query()->create($data + ['tenant_id' => $tenantId, 'rule_set_id' => $rules->id, 'created_by_user_id' => $request->user('tenant')->id])->refresh();
        app(PayrollRunService::class)->build($run);

        return redirect('/payroll/runs/'.$run->id);
    }

    public function showRun(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $run = $this->run($request);

        $slips = Payslip::query()->where('payroll_run_id', $run->id)->with('employee:id,tenant_id,employee_number,first_name,middle_name,last_name')->orderBy('id')->get();

        return Inertia::render('Company/Payroll/Run', [
            'run' => ['id' => $run->id, 'year' => $run->year, 'month' => $run->month, 'status' => $run->status, 'approved_at' => $run->approved_at?->toDateTimeString()],
            'rule_name' => PayrollRuleSet::where('tenant_id', $tenantId)->whereKey($run->rule_set_id)->value('name'),
            'payslips' => $slips->map(fn (Payslip $p) => ['id' => $p->id, 'employee' => $p->employee?->full_name, 'employee_number' => $p->employee?->employee_number] + $p->only(self::PAYSLIP_FIELDS))->values(),
            'totals' => ['gross' => round((float) $slips->sum('gross'), 2), 'tax' => round((float) $slips->sum('income_tax'), 2), 'insurance_employee' => round((float) $slips->sum('insurance_employee'), 2), 'insurance_employer' => round((float) $slips->sum('insurance_employer'), 2), 'net' => round((float) $slips->sum('net_pay'), 2)],
            'adjustments' => PayrollAdjustment::query()->where('payroll_run_id', $run->id)->orderBy('id')->get(['id', 'employee_id', 'kind', 'label', 'amount', 'taxable']),
            'employees' => Employee::query()->where('tenant_id', $tenantId)->whereIn('id', $slips->pluck('employee_id'))->orderBy('first_name')->get(['id', 'tenant_id', 'first_name', 'middle_name', 'last_name'])->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name])->values(),
            'abilities' => ['manage' => (bool) $request->user('tenant')?->can('payroll.manage')],
        ]);
    }

    public function recalculate(Request $request): RedirectResponse
    {
        $run = $this->run($request);
        $skipped = app(PayrollRunService::class)->build($run);

        return back()->with('status', $skipped === [] ? 'Recalculated.' : 'Recalculated. Left out: '.implode('; ', $skipped));
    }

    public function approve(Request $request): RedirectResponse
    {
        $run = $this->run($request);
        abort_unless($run->status === PayrollRun::DRAFT, 422, 'Already approved.');

        $run->forceFill(['status' => PayrollRun::APPROVED, 'approved_by_user_id' => $request->user('tenant')->id, 'approved_at' => now()])->save();

        return back()->with('status', 'Run approved and locked.');
    }

    public function destroyRun(Request $request): RedirectResponse
    {
        $run = $this->run($request);
        abort_unless($run->status === PayrollRun::DRAFT, 422, 'An approved run cannot be deleted.');

        $run->delete();

        return redirect('/payroll')->with('status', 'Draft run deleted.');
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $run = $this->run($request);
        abort_unless($run->status === PayrollRun::DRAFT, 422, 'Only a draft run can change.');

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'kind' => ['required', Rule::in(['earning', 'deduction'])],
            'label' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'taxable' => ['required', 'boolean'],
        ]);

        PayrollAdjustment::query()->create($data + ['tenant_id' => $tenantId, 'payroll_run_id' => $run->id, 'created_by_user_id' => $request->user('tenant')->id]);
        app(PayrollRunService::class)->build($run);

        return back()->with('status', 'Adjustment added.');
    }

    public function destroyAdjustment(Request $request): RedirectResponse
    {
        $run = $this->run($request);
        abort_unless($run->status === PayrollRun::DRAFT, 422, 'Only a draft run can change.');
        $adjustment = PayrollAdjustment::query()->where('payroll_run_id', $run->id)->findOrFail($request->route('adjustment'));
        $adjustment->delete();
        app(PayrollRunService::class)->build($run);

        return back()->with('status', 'Adjustment removed.');
    }

    public function export(Request $request): StreamedResponse
    {
        $run = $this->run($request);

        $rows = Payslip::query()->where('payroll_run_id', $run->id)->with('employee:id,tenant_id,employee_number,first_name,middle_name,last_name')->orderBy('id')->get();
        $name = sprintf('payroll-%04d-%02d.csv', $run->year, $run->month);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['employee_number', 'employee'], self::PAYSLIP_FIELDS));
            foreach ($rows as $p) {
                fputcsv($out, array_map([$this, 'csvSafe'], array_merge([$p->employee?->employee_number, $p->employee?->full_name], array_values($p->only(self::PAYSLIP_FIELDS)))));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** Stop spreadsheet formula injection from names. */
    public function csvSafe(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }

    private function run(Request $request): PayrollRun
    {
        return PayrollRun::query()->where('tenant_id', $this->tenantId())->findOrFail($request->route('run'));
    }

    private function activeRules(string $tenantId, bool $create): ?PayrollRuleSet
    {
        $rules = PayrollRuleSet::query()->where('tenant_id', $tenantId)->orderByDesc('effective_from')->orderByDesc('id')->first();

        if ($rules === null && $create) {
            $rules = PayrollRuleSet::query()->create(['tenant_id' => $tenantId] + EgyptRuleDefaults::attributes());
        }

        return $rules;
    }

    private function rulesPayload(PayrollRuleSet $r): array
    {
        return $r->only(['id', 'name', 'country', 'insurance_employee_rate', 'insurance_employer_rate', 'insurable_min', 'insurable_max', 'personal_exemption_annual', 'rounding_step', 'after_tax_levy_rate', 'after_tax_levy_label', 'month_days_basis', 'tax_tables', 'sources', 'open_questions'])
            + ['effective_from' => $r->effective_from->toDateString(), 'confirmed' => $r->isConfirmed(), 'confirmed_at' => $r->confirmed_at?->toDateTimeString()];
    }

    private function validateTables(mixed $tables): void
    {
        $fail = fn (string $m) => abort(422, 'Tax tables: '.$m);
        is_array($tables) && $tables !== [] || $fail('expected a list of tables.');

        foreach ($tables as $t) {
            is_array($t) && array_key_exists('up_to', $t) && is_array($t['bands'] ?? null) && $t['bands'] !== [] || $fail('each table needs up_to and bands.');
            $last = 0;
            foreach ($t['bands'] as $b) {
                is_array($b) && count($b) === 2 && is_numeric($b[1]) && $b[1] >= 0 && $b[1] <= 1 || $fail('each band is [upper limit, rate between 0 and 1].');
                if ($b[0] !== null) {
                    is_numeric($b[0]) && $b[0] > $last || $fail('band limits must increase.');
                    $last = $b[0];
                }
            }
            end($t['bands'])[0] === null || $fail('the last band of each table needs a null limit.');
        }

        end($tables)['up_to'] === null || $fail('the last table needs a null up_to.');
    }
}
